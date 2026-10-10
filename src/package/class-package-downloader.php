<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Package;

use ScottOffen\WpGithubUpdater\Internal\Http\HttpClient;
use ScottOffen\WpGithubUpdater\Internal\Http\HttpResponse;
use ScottOffen\WpGithubUpdater\Internal\Http\SafeUrl;
use ScottOffen\WpGithubUpdater\Internal\Platform\Files;
use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;
use ScottOffen\WpGithubUpdater\Internal\Release\Release;
use ScottOffen\WpGithubUpdater\Internal\Token\TokenStore;

/**
 * A downloader for saving the update asset of a release in a temporary file and
 * verifying the digest of the file.
 *
 * @internal
 */
final class PackageDownloader
{
    private const API_HOST = 'api.github.com';
    private const API_VERSION = '2022-11-28';
    private const USER_AGENT = 'scottoffen-wp-github-updater';
    private const MAX_REDIRECTS = 5;

    /**
     * The pattern for the path of an asset on the GitHub API: the owner and the
     * repository, or the repository ID, followed by `/releases/assets/` and
     * the asset ID.
     */
    private const ASSET_PATH = '#^/(?:repos/[A-Za-z0-9][A-Za-z0-9-]*/[A-Za-z0-9._-]+|repositories/\d+)/releases/assets/\d+$#D';

    public function __construct(
        private readonly HttpClient $http,
        private readonly Files $files,
        private readonly TokenStore $tokens,
    ) {
    }

    /**
     * Downloads the update asset of a release to a temporary file.
     *
     * A request to an asset address on the GitHub API sends the token, the
     * `Accept` header, and the `X-GitHub-Api-Version` header. A request to any
     * other address sends only the `User-Agent` header. A redirect is followed
     * up to five times. When the status is 200 to 299, the SHA-256 digest of
     * the file is compared with the `assetDigest` value, but only when that
     * value is `sha256:` followed by 64 hexadecimal characters.
     *
     * @param Release $release The release. The `assetUrl` and `assetDigest`
     *     values are used.
     *
     * @return DownloadResult On success, the path of the temporary file.
     *     Otherwise, there is no file, and the outcome is `REFUSED` when the
     *     asset address isn't an asset address on the GitHub API, `FILE_ERROR`
     *     when a temporary file can't be created or the file can't be hashed,
     *     `NETWORK` when no response is returned, `AUTH`, `NOT_FOUND`, or
     *     `RATE_LIMITED` for the matching error statuses from the GitHub API,
     *     `BAD_RESPONSE` for a redirect that isn't followed or any other status
     *     that isn't 200 to 299, and `DIGEST_MISMATCH` when the digests differ.
     */
    public function download(Release $release): DownloadResult
    {
        if (!$this->isAssetAddress($release->assetUrl)) {
            return new DownloadResult(null, Outcome::REFUSED);
        }

        $path = $this->files->tempFile();

        if ($path === null) {
            return new DownloadResult(null, Outcome::FILE_ERROR);
        }

        $token = $this->tokens->get();
        $url = $release->assetUrl;
        $redirects = 0;

        while (true) {
            $onApi = $this->isAssetAddress($url);
            $response = $this->http->download($url, $this->headers($onApi, $token), $path);

            if (!$response->isRedirect()) {
                break;
            }

            $next = $redirects++ < self::MAX_REDIRECTS ? $this->redirectTarget($response) : null;

            if ($next === null) {
                return $this->fail($path, Outcome::BAD_RESPONSE, $response->status);
            }

            $url = $next;
        }

        if ($response->failed()) {
            return $this->fail($path, Outcome::NETWORK, 0);
        }

        if ($response->status < 200 || $response->status > 299) {
            return $this->fail($path, $this->outcomeForError($response, $onApi), $response->status);
        }

        $outcome = $this->verify($path, $release->assetDigest);

        return $outcome === Outcome::OK
            ? new DownloadResult($path, Outcome::OK, $response->status)
            : $this->fail($path, $outcome, $response->status);
    }

    /**
     * Indicates whether a URL is an asset address on the GitHub API.
     *
     * @return bool True when `SafeUrl::parse()` accepts the URL, the host is
     *     `api.github.com`, and the path matches the asset path pattern.
     */
    private function isAssetAddress(string $url): bool
    {
        $parts = SafeUrl::parse($url);

        return $parts !== null
            && $parts['host'] === self::API_HOST
            && preg_match(self::ASSET_PATH, $parts['path']) === 1;
    }

    /**
     * Returns the request headers.
     *
     * @param bool $onApi True when the address is an asset address on the
     *     GitHub API.
     * @param string|null $token The token to send.
     *
     * @return array<string, string> The `User-Agent` header. When `$onApi` is
     *     true, also the `Accept` and `X-GitHub-Api-Version` headers, and the
     *     `Authorization` header when the token isn't null or empty.
     */
    private function headers(bool $onApi, ?string $token): array
    {
        $headers = ['User-Agent' => self::USER_AGENT];

        if (!$onApi) {
            return $headers;
        }

        $headers['Accept'] = 'application/octet-stream';
        $headers['X-GitHub-Api-Version'] = self::API_VERSION;

        if ($token !== null && $token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    /**
     * Determines the address of a redirect from the response.
     *
     * @return string|null The value of the `Location` header, or null when
     *     there is no `Location` header or `SafeUrl::parse()` rejects the
     *     value.
     */
    private function redirectTarget(HttpResponse $response): ?string
    {
        $location = $response->header('location');

        return $location !== null && SafeUrl::parse($location) !== null ? $location : null;
    }

    /**
     * Maps an error status to an `Outcome` code.
     *
     * @param bool $onApi True when the address is an asset address on the
     *     GitHub API.
     *
     * @return string `BAD_RESPONSE` when `$onApi` is false. Otherwise, `AUTH`
     *     for a 401, `NOT_FOUND` for a 404, `RATE_LIMITED` for a 429, and
     *     `BAD_RESPONSE` for any other status. A 403 gives `RATE_LIMITED` when
     *     the `x-ratelimit-remaining` header is `0` or the response has a
     *     `retry-after` header, and `AUTH` otherwise.
     */
    private function outcomeForError(HttpResponse $response, bool $onApi): string
    {
        if (!$onApi) {
            return Outcome::BAD_RESPONSE;
        }

        return match ($response->status) {
            401 => Outcome::AUTH,
            404 => Outcome::NOT_FOUND,
            429 => Outcome::RATE_LIMITED,
            403 => $response->header('x-ratelimit-remaining') === '0' || $response->header('retry-after') !== null
                ? Outcome::RATE_LIMITED
                : Outcome::AUTH,
            default => Outcome::BAD_RESPONSE,
        };
    }

    /**
     * Compares the SHA-256 digest of a file with a digest.
     *
     * @param string $digest The digest to compare with.
     *
     * @return string `OK` when the digests are equal, and also when `$digest`
     *     isn't `sha256:` followed by 64 hexadecimal characters, in which case
     *     the file isn't hashed. `FILE_ERROR` when the file can't be hashed,
     *     and `DIGEST_MISMATCH` when the digests differ.
     */
    private function verify(string $path, string $digest): string
    {
        if (preg_match('/^sha256:([0-9a-fA-F]{64})$/D', $digest, $matches) !== 1) {
            return Outcome::OK;
        }

        $actual = $this->files->hash($path, 'sha256');

        if ($actual === null) {
            return Outcome::FILE_ERROR;
        }

        return hash_equals(strtolower($matches[1]), $actual) ? Outcome::OK : Outcome::DIGEST_MISMATCH;
    }

    /**
     * Deletes the file and returns the result for a download that failed.
     *
     * @return DownloadResult A result with no path, the outcome, and the
     *     status.
     */
    private function fail(string $path, string $outcome, int $status): DownloadResult
    {
        $this->files->delete($path);

        return new DownloadResult(null, $outcome, $status);
    }
}
