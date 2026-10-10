<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Package;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Internal\Http\HttpResponse;
use ScottOffen\WpGithubUpdater\Internal\Package\DownloadResult;
use ScottOffen\WpGithubUpdater\Internal\Package\PackageDownloader;
use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;
use ScottOffen\WpGithubUpdater\Internal\Release\Release;
use ScottOffen\WpGithubUpdater\Internal\Token\TokenStore;
use ScottOffen\WpGithubUpdater\Tests\Support\FakeConstants;
use ScottOffen\WpGithubUpdater\Tests\Support\FakeFiles;
use ScottOffen\WpGithubUpdater\Tests\Support\FakeHttpClient;
use ScottOffen\WpGithubUpdater\Tests\Support\FakeOptionStore;

final class PackageDownloaderTest extends TestCase
{
    private const ASSET = 'https://api.github.com/repos/owner/repo/releases/assets/42';
    private const STORAGE = 'https://release-assets.githubusercontent.com/github-production-release-asset/42?sp=r&sig=abc%2Fdef';
    private const BODY = 'PK-the-zip-bytes';
    private const TEMP = '/tmp/fake-temp-1';

    private FakeFiles $files;

    private FakeHttpClient $http;

    private FakeConstants $constants;

    protected function setUp(): void
    {
        $this->files = new FakeFiles();
        $this->http = new FakeHttpClient($this->files);
        $this->constants = new FakeConstants();
    }

    private function downloader(): PackageDownloader
    {
        return new PackageDownloader($this->http, $this->files, new TokenStore(new FakeOptionStore(), $this->constants, 'fdcrm'));
    }

    private function release(string $url = self::ASSET, string $digest = ''): Release
    {
        return new Release('v1.0.0', '1.0.0', '', '', '', 42, 'my-plugin-1.0.0.zip', $url, 16, $digest);
    }

    private function redirect(string $location, int $status = 302): HttpResponse
    {
        return new HttpResponse($status, ['Location' => $location]);
    }

    private function withToken(): void
    {
        $this->constants->define('FDCRM_GITHUB_TOKEN', 'secret-token');
    }

    /**
     * Indicates whether a header of a recorded request contains the token
     * `secret-token`.
     *
     * @param array{headers: array<string, string>} $request The recorded
     *     request.
     *
     * @return bool True when a header value contains the token.
     */
    private function carriesToken(array $request): bool
    {
        foreach ($request['headers'] as $value) {
            if (str_contains((string) $value, 'secret-token')) {
                return true;
            }
        }

        return false;
    }

    public function testAPrivateDownloadSendsTheTokenToTheApiAndNoneToTheStorageHost(): void
    {
        $this->withToken();
        $this->http->queue($this->redirect(self::STORAGE), new HttpResponse(200, [], self::BODY));

        $result = $this->downloader()->download($this->release());

        $this->assertTrue($result->isOk());
        $this->assertSame(Outcome::OK, $result->outcome);
        $this->assertSame(200, $result->status);
        $this->assertSame(self::TEMP, $result->path);
        $this->assertSame(self::BODY, $this->files->contents(self::TEMP));

        $requests = $this->http->requests();

        $this->assertCount(2, $requests);
        $this->assertSame(self::ASSET, $requests[0]['url']);
        $this->assertSame('Bearer secret-token', $requests[0]['headers']['Authorization']);
        $this->assertSame('application/octet-stream', $requests[0]['headers']['Accept']);
        $this->assertSame(self::STORAGE, $requests[1]['url']);
        $this->assertFalse($this->carriesToken($requests[1]));
        $this->assertSame(['User-Agent' => 'scottoffen-wp-github-updater'], $requests[1]['headers']);
        $this->assertSame(self::TEMP, $requests[0]['destination']);
        $this->assertSame(self::TEMP, $requests[1]['destination']);
    }

    public function testAPublicDownloadSendsNoAuthorizationAnywhere(): void
    {
        $this->http->queue($this->redirect(self::STORAGE), new HttpResponse(200, [], self::BODY));

        $result = $this->downloader()->download($this->release());

        $this->assertTrue($result->isOk());

        foreach ($this->http->requests() as $request) {
            $this->assertArrayNotHasKey('Authorization', $request['headers']);
        }
    }

    public function testAnApiResponseWithTheFileItselfRequiresNoRedirect(): void
    {
        $this->withToken();
        $this->http->queue(new HttpResponse(200, [], self::BODY));

        $result = $this->downloader()->download($this->release());

        $this->assertTrue($result->isOk());
        $this->assertSame(self::BODY, $this->files->contents(self::TEMP));
        $this->assertCount(1, $this->http->requests());
    }

    public function testEveryRedirectStatusIsFollowed(): void
    {
        foreach ([301, 302, 303, 307, 308] as $status) {
            $this->setUp();
            $this->http->queue($this->redirect(self::STORAGE, $status), new HttpResponse(200, [], self::BODY));

            $this->assertTrue($this->downloader()->download($this->release())->isOk(), (string) $status);
        }
    }

    public function testARedirectBackToAnAssetAddressOnTheApiSendsTheTokenForThatHopOnly(): void
    {
        $this->withToken();
        $moved = 'https://api.github.com/repositories/9001/releases/assets/42';
        $this->http->queue($this->redirect($moved, 301), $this->redirect(self::STORAGE), new HttpResponse(200, [], self::BODY));

        $result = $this->downloader()->download($this->release());

        $this->assertTrue($result->isOk());

        $requests = $this->http->requests();

        $this->assertSame('Bearer secret-token', $requests[1]['headers']['Authorization']);
        $this->assertFalse($this->carriesToken($requests[2]));
    }

    public function testTheTokenGoesOnlyToAssetAddressesOnTheApi(): void
    {
        $this->withToken();

        $targets = [
            self::STORAGE,
            'https://api.github.com/user',
            'https://api.github.com/repos/owner/repo/releases/latest',
            'https://api.github.com/repos/owner/repo/releases/assets/abc',
            'https://api.github.com/repos/owner/repo/releases/assets/42/extra',
            'https://api.github.com.evil.example/repos/owner/repo/releases/assets/42',
            'https://evil.example/repos/owner/repo/releases/assets/42',
            'https://uploads.github.com/repos/owner/repo/releases/assets/42',
            'https://github.com/owner/repo/releases/download/v1.0.0/my-plugin-1.0.0.zip',
        ];

        foreach ($targets as $target) {
            $this->setUp();
            $this->withToken();
            $this->http->queue($this->redirect($target), new HttpResponse(200, [], self::BODY));

            $this->downloader()->download($this->release());

            $requests = $this->http->requests();

            $this->assertTrue($this->carriesToken($requests[0]), $target);
            $this->assertCount(2, $requests, $target);
            $this->assertFalse($this->carriesToken($requests[1]), $target);
        }
    }

    public function testARedirectToAnAddressThatIsNotSafeIsRefusedWithoutAnotherRequest(): void
    {
        $this->withToken();

        $bad = [
            'http://release-assets.githubusercontent.com/x',
            'https://user:pass@example.com/x',
            'https://example.com:8443/x',
            'https://example.com/../etc/passwd',
            '/relative/path',
            '//example.com/x',
            'https://example.com/a b',
            "https://example.com/x\r\nX-Evil: 1",
            'ftp://example.com/x',
            '',
        ];

        foreach ($bad as $location) {
            $this->setUp();
            $this->withToken();
            $this->http->queue($this->redirect($location), new HttpResponse(200, [], self::BODY));

            $result = $this->downloader()->download($this->release());

            $this->assertSame(Outcome::BAD_RESPONSE, $result->outcome, $location);
            $this->assertSame(302, $result->status, $location);
            $this->assertNull($result->path, $location);
            $this->assertCount(1, $this->http->requests(), $location);
            $this->assertFalse($this->files->exists(self::TEMP), $location);
        }
    }

    public function testARedirectWithNoLocationIsABadResponse(): void
    {
        $this->http->queue(new HttpResponse(302), new HttpResponse(200, [], self::BODY));

        $result = $this->downloader()->download($this->release());

        $this->assertSame(Outcome::BAD_RESPONSE, $result->outcome);
        $this->assertCount(1, $this->http->requests());
        $this->assertFalse($this->files->exists(self::TEMP));
    }

    public function testUpToFiveRedirectsAreFollowedAndASixthIsRefused(): void
    {
        $five = array_fill(0, 5, $this->redirect(self::STORAGE));
        $this->http->queue(...$five);
        $this->http->queue(new HttpResponse(200, [], self::BODY));

        $this->assertTrue($this->downloader()->download($this->release())->isOk());
        $this->assertCount(6, $this->http->requests());

        $this->setUp();
        $six = array_fill(0, 6, $this->redirect(self::STORAGE));
        $this->http->queue(...$six);
        $this->http->queue(new HttpResponse(200, [], self::BODY));

        $result = $this->downloader()->download($this->release());

        $this->assertSame(Outcome::BAD_RESPONSE, $result->outcome);
        $this->assertCount(6, $this->http->requests());
        $this->assertFalse($this->files->exists(self::TEMP));
    }

    public function testErrorsFromTheApiMapToOutcomesAndLeaveNoFile(): void
    {
        $cases = [
            [new HttpResponse(401), Outcome::AUTH],
            [new HttpResponse(404), Outcome::NOT_FOUND],
            [new HttpResponse(429), Outcome::RATE_LIMITED],
            [new HttpResponse(403, ['X-RateLimit-Remaining' => '0']), Outcome::RATE_LIMITED],
            [new HttpResponse(403, ['Retry-After' => '60']), Outcome::RATE_LIMITED],
            [new HttpResponse(403, ['X-RateLimit-Remaining' => '4000']), Outcome::AUTH],
            [new HttpResponse(500), Outcome::BAD_RESPONSE],
            [new HttpResponse(410), Outcome::BAD_RESPONSE],
        ];

        foreach ($cases as [$response, $outcome]) {
            $this->setUp();
            $this->http->queue($response);

            $result = $this->downloader()->download($this->release());

            $label = $response->status . ' ' . $outcome;
            $this->assertFalse($result->isOk(), $label);
            $this->assertSame($outcome, $result->outcome, $label);
            $this->assertSame($response->status, $result->status, $label);
            $this->assertNull($result->path, $label);
            $this->assertFalse($this->files->exists(self::TEMP), $label);
        }
    }

    public function testEveryErrorFromTheStorageHostIsABadResponse(): void
    {
        foreach ([401, 403, 404, 429, 500] as $status) {
            $this->setUp();
            $this->http->queue($this->redirect(self::STORAGE), new HttpResponse($status));

            $result = $this->downloader()->download($this->release());

            $this->assertSame(Outcome::BAD_RESPONSE, $result->outcome, (string) $status);
            $this->assertSame($status, $result->status, (string) $status);
            $this->assertFalse($this->files->exists(self::TEMP), (string) $status);
        }
    }

    public function testNoResponseIsANetworkProblemOnEitherHost(): void
    {
        $this->http->queue(HttpResponse::failure('timed out'));
        $first = $this->downloader()->download($this->release());

        $this->setUp();
        $this->http->queue($this->redirect(self::STORAGE), HttpResponse::failure('timed out'));
        $second = $this->downloader()->download($this->release());

        $this->assertSame(Outcome::NETWORK, $first->outcome);
        $this->assertSame(Outcome::NETWORK, $second->outcome);
        $this->assertSame(0, $second->status);
        $this->assertFalse($this->files->exists(self::TEMP));
    }

    public function testADigestThatMatchesLetsTheDownloadThrough(): void
    {
        $digest = 'sha256:' . hash('sha256', self::BODY);
        $this->http->queue($this->redirect(self::STORAGE), new HttpResponse(200, [], self::BODY));

        $result = $this->downloader()->download($this->release(self::ASSET, $digest));

        $this->assertTrue($result->isOk());
        $this->assertSame(self::BODY, $this->files->contents(self::TEMP));
    }

    public function testADigestInUppercaseHexStillMatches(): void
    {
        $digest = 'sha256:' . strtoupper(hash('sha256', self::BODY));
        $this->http->queue(new HttpResponse(200, [], self::BODY));

        $this->assertTrue($this->downloader()->download($this->release(self::ASSET, $digest))->isOk());
    }

    public function testADigestThatDoesNotMatchFailsTheDownloadAndDeletesTheFile(): void
    {
        $digest = 'sha256:' . hash('sha256', 'something else');
        $this->http->queue($this->redirect(self::STORAGE), new HttpResponse(200, [], self::BODY));

        $result = $this->downloader()->download($this->release(self::ASSET, $digest));

        $this->assertFalse($result->isOk());
        $this->assertSame(Outcome::DIGEST_MISMATCH, $result->outcome);
        $this->assertSame(200, $result->status);
        $this->assertNull($result->path);
        $this->assertFalse($this->files->exists(self::TEMP));
    }

    public function testADigestIsNotCheckedWhenThereIsNoneOrItCannotBeRead(): void
    {
        $unreadable = [
            '',
            'sha1:' . sha1('wrong'),
            'sha256:not-hex',
            'sha256:' . str_repeat('a', 63),
            'md5:abc',
            'garbage',
        ];

        foreach ($unreadable as $digest) {
            $this->setUp();
            $this->http->queue(new HttpResponse(200, [], self::BODY));

            $result = $this->downloader()->download($this->release(self::ASSET, $digest));

            $this->assertTrue($result->isOk(), $digest);
        }
    }

    public function testAnAssetAddressThatIsNotOnTheApiIsRefusedBeforeAnyRequestOrFile(): void
    {
        $this->withToken();

        $bad = [
            'https://evil.example/repos/owner/repo/releases/assets/42',
            'http://api.github.com/repos/owner/repo/releases/assets/42',
            'https://api.github.com.evil.example/repos/owner/repo/releases/assets/42',
            'https://api.github.com:8443/repos/owner/repo/releases/assets/42',
            'https://user@api.github.com/repos/owner/repo/releases/assets/42',
            'https://api.github.com/repos/owner/repo/releases/latest',
            'https://api.github.com/repos/owner/repo/releases/assets/abc',
            'https://api.github.com/repos/../user/releases/assets/42',
            'https://api.github.com/user',
            'https://github.com/owner/repo/releases/download/v1.0.0/my-plugin-1.0.0.zip',
            '/repos/owner/repo/releases/assets/42',
            '',
        ];

        foreach ($bad as $url) {
            $this->setUp();
            $this->withToken();

            $result = $this->downloader()->download($this->release($url));

            $this->assertSame(Outcome::REFUSED, $result->outcome, $url);
            $this->assertNull($result->path, $url);
            $this->assertSame([], $this->http->requests(), $url);
            $this->assertFalse($this->files->exists(self::TEMP), $url);
        }
    }

    public function testAssetAddressesByRepositoryNameOrIdAreAccepted(): void
    {
        foreach ([
            'https://api.github.com/repos/owner/repo/releases/assets/42',
            'https://api.github.com/repos/Some-Org/my.repo_name-2/releases/assets/9',
            'https://api.github.com/repositories/123456/releases/assets/42',
        ] as $url) {
            $this->setUp();
            $this->http->queue(new HttpResponse(200, [], self::BODY));

            $this->assertTrue($this->downloader()->download($this->release($url))->isOk(), $url);
        }
    }

    public function testNoTemporaryFileIsAFileError(): void
    {
        $this->files->refuseTempFiles();
        $this->http->queue(new HttpResponse(200, [], self::BODY));

        $result = $this->downloader()->download($this->release());

        $this->assertSame(Outcome::FILE_ERROR, $result->outcome);
        $this->assertNull($result->path);
        $this->assertSame([], $this->http->requests());
    }

    public function testADownloadResultIsOkOnlyWithAnOkOutcomeAndAPath(): void
    {
        $this->assertTrue((new DownloadResult('/tmp/x', Outcome::OK, 200))->isOk());
        $this->assertFalse((new DownloadResult(null, Outcome::OK, 200))->isOk());
        $this->assertFalse((new DownloadResult('/tmp/x', Outcome::NETWORK))->isOk());
        $this->assertFalse((new DownloadResult(null, Outcome::NETWORK))->isOk());
    }

    public function testTheTokenIsReadWhenTheDownloadStartsNotWhenTheDownloaderIsBuilt(): void
    {
        $downloader = $this->downloader();
        $this->withToken();
        $this->http->queue(new HttpResponse(200, [], self::BODY));

        $downloader->download($this->release());

        $this->assertSame('Bearer secret-token', $this->http->requests()[0]['headers']['Authorization']);
    }
}
