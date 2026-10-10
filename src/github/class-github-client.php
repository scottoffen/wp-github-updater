<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Github;

use InvalidArgumentException;
use ScottOffen\WpGithubUpdater\Internal\Http\HttpClient;
use ScottOffen\WpGithubUpdater\Internal\Http\HttpResponse;
use ScottOffen\WpGithubUpdater\Internal\Http\SafeUrl;
use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;

/**
 * Requests the releases of one repository from the GitHub API and turns the
 * response into an `Outcome` code.
 *
 * The client doesn't read the token or the stored state. The token and the ETag
 * are arguments, so the client has no side effects. It doesn't download assets.
 *
 * GitHub responds with a permanent redirect, a 301, when a repository has been
 * renamed or transferred. The client follows a 301 or a 308 by hand, up to
 * three times, so a site that still uses the old name can reach the release
 * that fixes it. A temporary redirect is not followed. Because every request
 * sends the token and the ETag, the client follows a redirect only to an
 * address under `https://api.github.com/repos/` or
 * `https://api.github.com/repositories/`. The new address is not stored, so the
 * next check starts from the configured repository again.
 *
 * A repository that is transferred to another owner redirects there too. The
 * new address doesn't include the owner, so the client can't detect the
 * transfer. The owner of the plugin or theme controls the repository, so that
 * is acceptable.
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

    /**
     * The permanent redirects, which are the only ones that the client follows.
     */
    private const FOLLOWED_REDIRECTS = [301, 308];

    /** An owner, a slash, and a repository name that isn't `.` or `..`. */
    private const REPOSITORY_PATTERN = '#^[A-Za-z0-9][A-Za-z0-9-]*/(?!\.{1,2}$)[A-Za-z0-9._-]+$#D';

    /**
     * @param HttpClient $http Sends the request.
     * @param string $repository The repository, written `owner/repository`.
     *
     * @throws InvalidArgumentException If the repository isn't written `owner/repository`.
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
     * Requests the newest releases.
     *
     * @param string|null $token The token to send. Null or empty sends none,
     *     which is enough for a public repository.
     * @param string|null $etag The ETag from the last response. Null or empty
     *     requests the full list, which a forced check uses.
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
     * Returns the address that a redirect points to, but only when it is safe
     * to send the token there. That means a safe address, as `SafeUrl` defines
     * it, on `api.github.com`, whose path starts with `/repos/` or
     * `/repositories/`.
     */
    private function redirectTarget(HttpResponse $response): ?string
    {
        $location = $response->header('location');
        $parts = $location === null ? null : SafeUrl::parse($location);

        if ($parts === null || $parts['host'] !== self::API_HOST) {
            return null;
        }

        if (!str_starts_with($parts['path'], '/repos/') && !str_starts_with($parts['path'], '/repositories/')) {
            return null;
        }

        return $location;
    }

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

    private function readList(HttpResponse $response): ReleaseListing
    {
        $releases = json_decode($response->body, true);

        if (!is_array($releases) || !array_is_list($releases)) {
            return new ReleaseListing(Outcome::BAD_RESPONSE, [], '', $response->status);
        }

        return new ReleaseListing(Outcome::OK, $releases, $response->header('etag') ?? '', $response->status);
    }

    /**
     * GitHub uses 403 both for an exhausted rate limit and for a token that
     * isn't allowed to read the repository. The rate limit headers distinguish
     * them.
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

    private function isRateLimited(HttpResponse $response): bool
    {
        return $response->header('x-ratelimit-remaining') === '0' || $response->header('retry-after') !== null;
    }
}
