<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Github;

use InvalidArgumentException;
use ScottOffen\WpGithubUpdater\Internal\Http\HttpClient;
use ScottOffen\WpGithubUpdater\Internal\Http\HttpResponse;
use ScottOffen\WpGithubUpdater\Internal\Http\SafeUrl;
use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;

/**
 * A client for requesting the releases of one repository from the GitHub API
 * and returning the result as a `ReleaseListing` that has an `Outcome` code.
 *
 * When a response has the status 301 or 308, the client sends the request
 * again, with the same headers, to the address in the `Location` header. It
 * follows up to three redirects, and only to an address on `api.github.com`
 * under `/repos/` or `/repositories/`. A redirect that isn't followed gives the
 * `BAD_RESPONSE` outcome. The new address isn't stored, so the next request
 * starts from the configured repository again.
 *
 * @internal
 */
final class GitHubClient
{
    private const API_HOST = 'api.github.com';
    private const API = 'https://' . self::API_HOST;
    private const API_VERSION = '2022-11-28';
    private const USER_AGENT = 'scottoffen-wp-github-updater';
    private const PER_PAGE = 30;
    private const MAX_REDIRECTS = 3;

    /** The permanent redirects that the client will follow. */
    private const FOLLOWED_REDIRECTS = [301, 308];

    /** An owner, a slash, and a repository name that isn't `.` or `..`. */
    private const REPOSITORY_PATTERN = '#^[A-Za-z0-9][A-Za-z0-9-]*/(?!\.{1,2}$)[A-Za-z0-9._-]+$#D';

    /**
     * Creates a client for one repository.
     *
     * @param HttpClient $http The HTTP client for the requests to the GitHub
     *     API.
     * @param string $repository The repository, written `owner/repository`.
     *
     * @throws InvalidArgumentException If the repository isn't written
     *     `owner/repository`.
     */
    public function __construct(
        private readonly HttpClient $http,
        private readonly string $repository,
    ) {
        if (preg_match(self::REPOSITORY_PATTERN, $repository) !== 1) {
            throw new InvalidArgumentException('The repository must be written "owner/repository".');
        }
    }

    /**
     * Requests the first page of releases from the GitHub API, 30 at most.
     *
     * @param string|null $token The token to send in the `Authorization`
     *     header. Null or an empty string sends no `Authorization` header.
     * @param string|null $etag The ETag to send in the `If-None-Match` header.
     *     Null or an empty string sends no `If-None-Match` header.
     *
     * @return ReleaseListing The result of the request. The outcome is `OK` for
     *     a 200 response that has a JSON list, `NOT_MODIFIED` for a 304
     *     response, and `NETWORK` when no response is returned. A 401 gives
     *     `AUTH`, a 404 gives `NOT_FOUND`, and a 429 gives `RATE_LIMITED`. A
     *     403 gives `RATE_LIMITED` or `AUTH` under the conditions in the
     *     description of the `outcomeForError()` method. Any other status, a
     *     redirect that isn't followed, and a body that isn't a JSON list give
     *     `BAD_RESPONSE`.
     */
    public function listReleases(?string $token = null, ?string $etag = null): ReleaseListing
    {
        $headers = [
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => self::API_VERSION,
            'User-Agent' => self::USER_AGENT,
        ];

        if ($token !== null && $token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        if ($etag !== null && $etag !== '') {
            $headers['If-None-Match'] = $etag;
        }

        $url = self::API . '/repos/' . $this->repository . '/releases?per_page=' . self::PER_PAGE;

        $response = $this->http->get($url, $headers);

        for ($redirects = 0; $response->isRedirect(); $redirects++) {
            $follow = $redirects < self::MAX_REDIRECTS && in_array($response->status, self::FOLLOWED_REDIRECTS, true);
            $next = $follow ? $this->redirectTarget($response) : null;

            if ($next === null) {
                return new ReleaseListing(Outcome::BAD_RESPONSE, [], '', $response->status);
            }

            $response = $this->http->get($next, $headers);
        }

        return $this->read($response, $etag ?? '');
    }

    /**
     * Determines the address of a redirect from the response.
     *
     * @return string|null The address in the `Location` header, or null when
     *     there is no `Location` header, the address doesn't meet the
     *     conditions of the `SafeUrl::parse()` method, the host isn't
     *     `api.github.com`, or the path starts with neither `/repos/` nor
     *     `/repositories/`.
     */
    private function redirectTarget(HttpResponse $response): ?string
    {
        $location = $response->header('location');
        $parts = $location === null ? null : SafeUrl::parse($location);

        // The next request sends the token, so only an address on the GitHub
        // API is accepted.
        if ($parts === null || $parts['host'] !== self::API_HOST) {
            return null;
        }

        // A `/repositories/` address has no owner, so the owner of the
        // repository at the new address isn't checked.
        if (!str_starts_with($parts['path'], '/repos/') && !str_starts_with($parts['path'], '/repositories/')) {
            return null;
        }

        return $location;
    }

    /**
     * Reads the response to the request for releases.
     *
     * @param HttpResponse $response The response to read.
     * @param string $sentEtag The ETag that was sent with the request, or an
     *     empty string.
     *
     * @return ReleaseListing The listing for the response. When no response is
     *     returned, the outcome is `NETWORK`. A 304 gives `NOT_MODIFIED`, with
     *     the ETag from the response or else the ETag that was sent. A 200
     *     gives the result of the `readList()` method, and any other status
     *     gives the code from the `outcomeForError()` method.
     */
    private function read(HttpResponse $response, string $sentEtag): ReleaseListing
    {
        $status = $response->status;

        if ($response->failed()) {
            return new ReleaseListing(Outcome::NETWORK);
        }

        if ($status === 304) {
            return new ReleaseListing(Outcome::NOT_MODIFIED, [], $response->header('etag') ?? $sentEtag, $status);
        }

        if ($status === 200) {
            return $this->readList($response);
        }

        return new ReleaseListing($this->outcomeForError($response), [], '', $status);
    }

    /**
     * Reads the body of a 200 response as a JSON list of releases.
     *
     * @return ReleaseListing The listing with the `OK` outcome, the decoded
     *     list, the ETag of the response, which is an empty string when there
     *     is none, and the status. A body that isn't a JSON list gives
     *     `BAD_RESPONSE`.
     */
    private function readList(HttpResponse $response): ReleaseListing
    {
        $releases = json_decode($response->body, true);

        if (!is_array($releases) || !array_is_list($releases)) {
            return new ReleaseListing(Outcome::BAD_RESPONSE, [], '', $response->status);
        }

        return new ReleaseListing(Outcome::OK, $releases, $response->header('etag') ?? '', $response->status);
    }

    /**
     * Maps an error status to an `Outcome` code.
     *
     * @return string `AUTH` for a 401, `NOT_FOUND` for a 404, `RATE_LIMITED`
     *     for a 429, and `BAD_RESPONSE` for any other status. A 403 gives
     *     `RATE_LIMITED` when the `isRateLimited()` method returns true and
     *     `AUTH` otherwise.
     */
    private function outcomeForError(HttpResponse $response): string
    {
        return match ($response->status) {
            401 => Outcome::AUTH,
            404 => Outcome::NOT_FOUND,
            429 => Outcome::RATE_LIMITED,
            403 => $this->isRateLimited($response) ? Outcome::RATE_LIMITED : Outcome::AUTH,
            default => Outcome::BAD_RESPONSE,
        };
    }

    /**
     * Indicates whether the headers of a response report a rate limit.
     *
     * @return bool True when the `x-ratelimit-remaining` header is `0` or the
     *     response has a `retry-after` header.
     */
    private function isRateLimited(HttpResponse $response): bool
    {
        return $response->header('x-ratelimit-remaining') === '0' || $response->header('retry-after') !== null;
    }
}
