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
 * @internal
 */
final class PackageDownloader
{
    private const API_HOST = 'api.github.com';
    private const API_VERSION = '2022-11-28';
    private const USER_AGENT = 'scottoffen-wp-github-updater';
    private const MAX_REDIRECTS = 5;


    private const ASSET_PATH = '#^/(?:repos/[A-Za-z0-9][A-Za-z0-9-]*/[A-Za-z0-9._-]+|repositories/\d+)/releases/assets/\d+$#D';

    public function __construct(
        private readonly HttpClient $http,
        private readonly Files $files,
        private readonly TokenStore $tokens,
    ) {
    }

    /**
     * @param Release $release The release whose asset to download.
     *
     * @return DownloadResult The path of the file on success. On failure, the
     *     code that gives the reason: `REFUSED` when the asset address isn't
     *     one that the updater will request, `FILE_ERROR` when there is no
     *     temporary file, `AUTH`, `NOT_FOUND`, `RATE_LIMITED`, `NETWORK`, or
     *     `BAD_RESPONSE` from the requests, and `DIGEST_MISMATCH` when the file
     *     isn't what GitHub reported.
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


    private function isAssetAddress(string $url): bool
    {
        $parts = SafeUrl::parse($url);

        return $parts !== null
            && $parts['host'] === self::API_HOST
            && preg_match(self::ASSET_PATH, $parts['path']) === 1;
    }

    /**
     * @return array<string, string>
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


    private function redirectTarget(HttpResponse $response): ?string
    {
        $location = $response->header('location');

        return $location !== null && SafeUrl::parse($location) !== null ? $location : null;
    }


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

    private function fail(string $path, string $outcome, int $status): DownloadResult
    {
        $this->files->delete($path);

        return new DownloadResult(null, $outcome, $status);
    }
}
