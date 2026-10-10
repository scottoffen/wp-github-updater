<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Github;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Github\GitHubClient;
use ScottOffen\WpGithubUpdater\Http\HttpResponse;
use ScottOffen\WpGithubUpdater\Release\Outcome;
use ScottOffen\WpGithubUpdater\Tests\Support\FakeFiles;
use ScottOffen\WpGithubUpdater\Tests\Support\FakeHttpClient;

final class GitHubClientTest extends TestCase
{
    private FakeHttpClient $http;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient(new FakeFiles());
    }

    private function client(string $repository = 'owner/repo'): GitHubClient
    {
        return new GitHubClient($this->http, $repository);
    }

    /** Queues one answer and returns what the client made of it. */
    private function answer(HttpResponse $response, ?string $token = null, ?string $etag = null): \ScottOffen\WpGithubUpdater\Github\ReleaseListing
    {
        $this->http->queue($response);

        return $this->client()->listReleases($token, $etag);
    }

    public function testItAsksForTheNewestThirtyReleasesOfTheRepository(): void
    {
        $this->answer(new HttpResponse(200, [], '[]'));

        $requests = $this->http->requests();

        $this->assertCount(1, $requests);
        $this->assertSame('GET', $requests[0]['method']);
        $this->assertSame('https://api.github.com/repos/owner/repo/releases?per_page=30', $requests[0]['url']);
        $this->assertNull($requests[0]['destination']);
    }

    public function testItSendsTheHeadersGitHubExpectsAndNoTokenOrEtagByDefault(): void
    {
        $this->answer(new HttpResponse(200, [], '[]'));

        $this->assertSame([
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'scottoffen-wp-github-updater',
        ], $this->http->requests()[0]['headers']);
    }

    public function testItSendsTheTokenAsABearerToken(): void
    {
        $this->answer(new HttpResponse(200, [], '[]'), 'secret-token');

        $this->assertSame('Bearer secret-token', $this->http->requests()[0]['headers']['Authorization']);
    }

    public function testAnEmptyTokenSendsNoAuthorizationHeader(): void
    {
        $this->answer(new HttpResponse(200, [], '[]'), '');

        $this->assertArrayNotHasKey('Authorization', $this->http->requests()[0]['headers']);
    }

    public function testItSendsTheEtagAsAConditionalRequest(): void
    {
        $this->answer(new HttpResponse(304), null, 'W/"abc"');

        $this->assertSame('W/"abc"', $this->http->requests()[0]['headers']['If-None-Match']);
    }

    public function testAnEmptyEtagSendsNoConditionalHeader(): void
    {
        $this->answer(new HttpResponse(200, [], '[]'), null, '');

        $this->assertArrayNotHasKey('If-None-Match', $this->http->requests()[0]['headers']);
    }

    public function testAListIsReadWithItsEtag(): void
    {
        $body = json_encode([['tag_name' => 'v1.0.0'], ['tag_name' => 'v0.9.0']]);

        $listing = $this->answer(new HttpResponse(200, ['ETag' => 'W/"new"'], (string) $body));

        $this->assertSame(Outcome::OK, $listing->outcome);
        $this->assertTrue($listing->isOk());
        $this->assertSame([['tag_name' => 'v1.0.0'], ['tag_name' => 'v0.9.0']], $listing->releases);
        $this->assertSame('W/"new"', $listing->etag);
        $this->assertSame(200, $listing->status);
    }

    public function testAnEmptyListIsStillOkAndHasNoEtagWhenGitHubSendsNone(): void
    {
        $listing = $this->answer(new HttpResponse(200, [], '[]'));

        $this->assertSame(Outcome::OK, $listing->outcome);
        $this->assertSame([], $listing->releases);
        $this->assertSame('', $listing->etag);
    }

    public function testAnAnswerThatIsNotAListOfReleasesIsABadResponse(): void
    {
        $bodies = ['', 'not json', 'null', '"text"', '42', '{"message":"hello"}', '{"0":{"a":1},"2":{"b":2}}'];

        foreach ($bodies as $body) {
            $this->http->queue(new HttpResponse(200, [], $body));
            $listing = $this->client()->listReleases();

            $this->assertSame(Outcome::BAD_RESPONSE, $listing->outcome, $body);
            $this->assertSame([], $listing->releases, $body);
            $this->assertSame(200, $listing->status, $body);
        }
    }

    public function testNotModifiedKeepsTheEtagThatWasSent(): void
    {
        $listing = $this->answer(new HttpResponse(304), null, 'W/"abc"');

        $this->assertSame(Outcome::NOT_MODIFIED, $listing->outcome);
        $this->assertFalse($listing->isOk());
        $this->assertSame([], $listing->releases);
        $this->assertSame('W/"abc"', $listing->etag);
        $this->assertSame(304, $listing->status);
    }

    public function testNotModifiedPrefersAnEtagGitHubSendsBack(): void
    {
        $listing = $this->answer(new HttpResponse(304, ['etag' => 'W/"fresh"']), null, 'W/"abc"');

        $this->assertSame('W/"fresh"', $listing->etag);
    }

    public function testTheErrorStatusesMapToOutcomes(): void
    {
        $cases = [
            401 => Outcome::AUTH,
            404 => Outcome::NOT_FOUND,
            429 => Outcome::RATE_LIMITED,
            403 => Outcome::AUTH,
            500 => Outcome::BAD_RESPONSE,
            502 => Outcome::BAD_RESPONSE,
            410 => Outcome::BAD_RESPONSE,
        ];

        foreach ($cases as $status => $outcome) {
            $listing = $this->answer(new HttpResponse($status, [], '{"message":"nope"}'));

            $this->assertSame($outcome, $listing->outcome, (string) $status);
            $this->assertSame($status, $listing->status, (string) $status);
            $this->assertSame([], $listing->releases, (string) $status);
            $this->assertSame('', $listing->etag, (string) $status);
        }
    }

    public function testA403WithNoRateLimitLeftIsRateLimited(): void
    {
        $listing = $this->answer(new HttpResponse(403, ['X-RateLimit-Remaining' => '0']));

        $this->assertSame(Outcome::RATE_LIMITED, $listing->outcome);
    }

    public function testA403WithRetryAfterIsRateLimited(): void
    {
        $listing = $this->answer(new HttpResponse(403, ['Retry-After' => '60']));

        $this->assertSame(Outcome::RATE_LIMITED, $listing->outcome);
    }

    public function testA403WithRateLimitLeftIsAnAuthProblem(): void
    {
        $listing = $this->answer(new HttpResponse(403, ['X-RateLimit-Remaining' => '4999']));

        $this->assertSame(Outcome::AUTH, $listing->outcome);
    }

    public function testNoAnswerIsANetworkProblem(): void
    {
        $listing = $this->answer(HttpResponse::failure('Could not resolve host'));

        $this->assertSame(Outcome::NETWORK, $listing->outcome);
        $this->assertSame(0, $listing->status);
        $this->assertSame([], $listing->releases);
    }

    private const MOVED = 'https://api.github.com/repositories/123456/releases?per_page=30';

    private function redirect(int $status = 301, string $location = self::MOVED): HttpResponse
    {
        return new HttpResponse($status, ['Location' => $location]);
    }

    public function testARedirectIsFollowedToTheNewAddress(): void
    {
        $this->http->queue($this->redirect(), new HttpResponse(200, ['ETag' => 'W/"moved"'], '[{"tag_name":"v2.0.0"}]'));

        $listing = $this->client()->listReleases('secret-token', 'W/"old"');

        $this->assertSame(Outcome::OK, $listing->outcome);
        $this->assertSame([['tag_name' => 'v2.0.0']], $listing->releases);
        $this->assertSame('W/"moved"', $listing->etag);

        $requests = $this->http->requests();

        $this->assertCount(2, $requests);
        $this->assertSame('https://api.github.com/repos/owner/repo/releases?per_page=30', $requests[0]['url']);
        $this->assertSame(self::MOVED, $requests[1]['url']);
        $this->assertSame($requests[0]['headers'], $requests[1]['headers']);
        $this->assertSame('Bearer secret-token', $requests[1]['headers']['Authorization']);
        $this->assertSame('W/"old"', $requests[1]['headers']['If-None-Match']);
    }

    public function testOnlyThePermanentRedirectsAreFollowed(): void
    {
        foreach ([301, 308] as $status) {
            $this->http->queue($this->redirect($status), new HttpResponse(200, [], '[]'));

            $this->assertSame(Outcome::OK, $this->client()->listReleases()->outcome, (string) $status);
        }
    }

    public function testTemporaryRedirectsAreABadResponseAndNothingIsFollowed(): void
    {
        foreach ([302, 303, 307] as $status) {
            $http = new FakeHttpClient(new FakeFiles());
            $http->queue($this->redirect($status), new HttpResponse(200, [], '[]'));

            $listing = (new GitHubClient($http, 'owner/repo'))->listReleases('secret-token');

            $this->assertSame(Outcome::BAD_RESPONSE, $listing->outcome, (string) $status);
            $this->assertSame($status, $listing->status, (string) $status);
            $this->assertCount(1, $http->requests(), (string) $status);
        }
    }

    public function testTheAnswerAfterARedirectIsReadLikeAnyOther(): void
    {
        $this->http->queue($this->redirect(), new HttpResponse(304));
        $notModified = $this->client()->listReleases(null, 'W/"abc"');

        $this->http->queue($this->redirect(), new HttpResponse(404));
        $notFound = $this->client()->listReleases();

        $this->assertSame(Outcome::NOT_MODIFIED, $notModified->outcome);
        $this->assertSame('W/"abc"', $notModified->etag);
        $this->assertSame(Outcome::NOT_FOUND, $notFound->outcome);
        $this->assertSame(404, $notFound->status);
    }

    public function testNoAnswerAfterARedirectIsANetworkProblem(): void
    {
        $this->http->queue($this->redirect(), HttpResponse::failure('timed out'));

        $this->assertSame(Outcome::NETWORK, $this->client()->listReleases()->outcome);
    }

    public function testUpToThreeRedirectsAreFollowed(): void
    {
        $this->http->queue($this->redirect(), $this->redirect(308), $this->redirect(301), new HttpResponse(200, [], '[]'));

        $listing = $this->client()->listReleases();

        $this->assertSame(Outcome::OK, $listing->outcome);
        $this->assertCount(4, $this->http->requests());
    }

    public function testAFourthRedirectIsABadResponseAndStopsTheRequests(): void
    {
        $this->http->queue($this->redirect(), $this->redirect(), $this->redirect(), $this->redirect(), new HttpResponse(200, [], '[]'));

        $listing = $this->client()->listReleases();

        $this->assertSame(Outcome::BAD_RESPONSE, $listing->outcome);
        $this->assertSame(301, $listing->status);
        $this->assertSame([], $listing->releases);
        $this->assertCount(4, $this->http->requests());
        $this->assertSame(1, $this->http->remaining());
    }

    public function testAnAddressUnderRepositoriesOrReposOnTheGitHubApiIsFollowed(): void
    {
        $good = [
            'https://api.github.com/repositories/1/releases',
            'https://api.github.com/repos/new-owner/new-name/releases?per_page=30',
            'https://API.GITHUB.COM/repos/new-owner/new-name/releases',
        ];

        foreach ($good as $location) {
            $this->http->queue($this->redirect(301, $location), new HttpResponse(200, [], '[]'));

            $this->assertSame(Outcome::OK, $this->client()->listReleases()->outcome, $location);
        }
    }

    public function testARedirectToAnywhereElseIsRefusedBeforeTheTokenIsSent(): void
    {
        $bad = [
            'https://evil.example/repos/owner/repo/releases',
            'http://api.github.com/repos/owner/repo/releases',
            'https://api.github.com.evil.example/repos/owner/repo/releases',
            'https://evil.example/https://api.github.com/repos/owner/repo/releases',
            'https://api.github.com:8443/repos/owner/repo/releases',
            'https://api.github.com:443/repos/owner/repo/releases',
            'https://user:pass@api.github.com/repos/owner/repo/releases',
            'https://api.github.com@evil.example/repos/owner/repo/releases',
            'https://api.github.com\\@evil.example/repos/owner/repo/releases',
            '/repos/owner/repo/releases',
            '//api.github.com/repos/owner/repo/releases',
            'repos/owner/repo/releases',
            'https://api.github.com/users/owner',
            'https://api.github.com/',
            'https://api.github.com/repos',
            'https://api.github.com/repositoriesevil/1',
            'https://api.github.com/repos/../user',
            'https://api.github.com/repos/%2e%2e/user',
            'https://api.github.com/repos/./owner/repo',
            'https://api.github.com/repos/owner\\..\\..\\user',
            'https://api.github.com/repos/owner/repo/releases and more',
            "https://api.github.com/repos/owner/repo/releases\r\nX-Evil: 1",
            'ftp://api.github.com/repos/owner/repo/releases',
            '',
        ];

        foreach ($bad as $location) {
            $http = new FakeHttpClient(new FakeFiles());
            $http->queue($this->redirect(301, $location), new HttpResponse(200, [], '[]'));

            $listing = (new GitHubClient($http, 'owner/repo'))->listReleases('secret-token');

            $this->assertSame(Outcome::BAD_RESPONSE, $listing->outcome, $location);
            $this->assertSame(301, $listing->status, $location);
            $this->assertCount(1, $http->requests(), $location);
        }
    }

    public function testARedirectWithNoLocationIsABadResponse(): void
    {
        $this->http->queue(new HttpResponse(301), new HttpResponse(200, [], '[]'));

        $listing = $this->client()->listReleases();

        $this->assertSame(Outcome::BAD_RESPONSE, $listing->outcome);
        $this->assertCount(1, $this->http->requests());
    }

    public function testARedirectIsNeverRememberedForTheNextCall(): void
    {
        $this->http->queue($this->redirect(), new HttpResponse(200, [], '[]'), new HttpResponse(200, [], '[]'));

        $this->client()->listReleases();
        $this->client()->listReleases();

        $urls = array_column($this->http->requests(), 'url');

        $this->assertSame([
            'https://api.github.com/repos/owner/repo/releases?per_page=30',
            self::MOVED,
            'https://api.github.com/repos/owner/repo/releases?per_page=30',
        ], $urls);
    }

    public function testRepositoriesWrittenOwnerSlashNameAreAccepted(): void
    {
        foreach (['owner/repo', 'Some-Org/my.repo_name-2', 'a/b', 'scottoffen/wp-github-updater', 'o/.github'] as $repository) {
            $this->assertInstanceOf(GitHubClient::class, $this->client($repository), $repository);
        }
    }

    public function testRepositoriesNotWrittenOwnerSlashNameAreRejected(): void
    {
        $bad = ['', 'owner', 'owner/', '/repo', 'owner/repo/extra', '-owner/repo', 'owner/.', 'owner/..', 'owner/re po', 'owner/repo?x=1', 'owner/repo#a', 'https://github.com/owner/repo', "owner/repo\n"];

        foreach ($bad as $repository) {
            try {
                $this->client($repository);
                $this->fail('Accepted: ' . var_export($repository, true));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testNothingIsRequestedWhenTheRepositoryIsRejected(): void
    {
        try {
            $this->client('not a repository');
        } catch (InvalidArgumentException) {
        }

        $this->assertSame([], $this->http->requests());
    }
}
