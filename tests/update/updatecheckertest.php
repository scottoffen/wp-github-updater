<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Update;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Internal\Github\GitHubClient;
use ScottOffen\WpGithubUpdater\Internal\Http\HttpResponse;
use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;
use ScottOffen\WpGithubUpdater\Internal\Release\ReleaseResolver;
use ScottOffen\WpGithubUpdater\Internal\Token\TokenStore;
use ScottOffen\WpGithubUpdater\Internal\Update\ReleaseCache;
use ScottOffen\WpGithubUpdater\Internal\Update\ReleaseState;
use ScottOffen\WpGithubUpdater\Internal\Update\UpdateChecker;
use ScottOffen\WpGithubUpdater\Tests\Support\FakeClock;
use ScottOffen\WpGithubUpdater\Tests\Support\FakeConstants;
use ScottOffen\WpGithubUpdater\Tests\Support\FakeFiles;
use ScottOffen\WpGithubUpdater\Tests\Support\FakeHttpClient;
use ScottOffen\WpGithubUpdater\Tests\Support\FakeOptionStore;

final class UpdateCheckerTest extends TestCase
{
    private const NOW = 1_800_000_000;
    private const HOUR = 3600;
    private const FRESH = 12 * 3600;
    private const OPTION = 'fdcrm_updater_release_state';

    private FakeHttpClient $http;

    private FakeOptionStore $options;

    private FakeConstants $constants;

    private FakeClock $clock;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient(new FakeFiles());
        $this->options = new FakeOptionStore();
        $this->constants = new FakeConstants();
        $this->clock = new FakeClock(self::NOW);
    }

    private function checker(): UpdateChecker
    {
        return new UpdateChecker(
            new GitHubClient($this->http, 'owner/repo'),
            new ReleaseResolver('my-plugin-*.zip'),
            new TokenStore($this->options, $this->constants, 'fdcrm'),
            new ReleaseCache($this->options, $this->clock, 'fdcrm'),
            $this->clock,
        );
    }

    /**
     * Returns the JSON for a list of releases with the tags, each with one
     * asset.
     *
     * @return string The JSON.
     */
    private function releases(string ...$tags): string
    {
        $list = [];

        foreach ($tags as $index => $tag) {
            $version = ltrim($tag, 'v');
            $id = 100 + $index;

            $list[] = [
                'tag_name' => $tag,
                'draft' => false,
                'prerelease' => false,
                'body' => 'Notes for ' . $tag,
                'published_at' => '2026-10-01T12:00:00Z',
                'html_url' => 'https://github.com/owner/repo/releases/tag/' . $tag,
                'assets' => [[
                    'id' => $id,
                    'name' => 'my-plugin-' . $version . '.zip',
                    'url' => 'https://api.github.com/repos/owner/repo/releases/assets/' . $id,
                    'size' => 2048,
                    'digest' => 'sha256:abc',
                ]],
            ];
        }

        return (string) json_encode($list);
    }

    private function listing(string $body, string $etag = 'W/"e1"'): HttpResponse
    {
        return new HttpResponse(200, ['ETag' => $etag], $body);
    }

    /**
     * Queues a listing with the release `v1.0.0` and calls the `latest()`
     * method of the checker.
     */
    private function firstCheck(UpdateChecker $checker): void
    {
        $this->http->queue($this->listing($this->releases('v1.0.0')));
        $checker->latest();
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        $stored = $this->options->get(self::OPTION);
        $this->assertIsArray($stored);

        return $stored;
    }

    public function testTheFirstCallSendsARequestAndFindsTheRelease(): void
    {
        $this->http->queue($this->listing($this->releases('v1.0.0')));

        $release = $this->checker()->latest();

        $this->assertNotNull($release);
        $this->assertSame('1.0.0', $release->version);
        $this->assertSame('Notes for v1.0.0', $release->notes);
        $this->assertCount(1, $this->http->requests());
    }

    public function testTheCheckLeavesAStateBehind(): void
    {
        $this->http->queue($this->listing($this->releases('v1.0.0'), 'W/"abc"'));
        $checker = $this->checker();

        $checker->latest();
        $state = $checker->state();

        $this->assertSame(self::NOW, $state->checkedAt);
        $this->assertSame(Outcome::OK, $state->outcome);
        $this->assertSame(200, $state->status);
        $this->assertSame('W/"abc"', $state->etag);
        $this->assertSame('1.0.0', $state->release?->version);
        $this->assertSame(['fdcrm_updater_release_state'], $this->options->names());
        $this->assertFalse($this->options->autoloads(self::OPTION));
    }

    public function testNothingIsRequestedWhileTheCacheIsFresh(): void
    {
        $checker = $this->checker();
        $this->firstCheck($checker);

        $this->clock->advance(self::FRESH - 1);

        $this->assertSame('1.0.0', $checker->latest()?->version);
        $this->assertCount(1, $this->http->requests());
    }

    public function testAStaleCacheSendsTheEtagAndANotModifiedResponseLeavesTheReleaseUnchanged(): void
    {
        $checker = $this->checker();
        $this->firstCheck($checker);
        $this->clock->advance(self::FRESH);
        $this->http->queue(new HttpResponse(304));

        $release = $checker->latest();

        $this->assertSame('1.0.0', $release?->version);
        $this->assertSame('W/"e1"', $this->http->requests()[1]['headers']['If-None-Match']);
        $this->assertSame(self::NOW + self::FRESH, $checker->state()->checkedAt);
        $this->assertSame(Outcome::OK, $checker->state()->outcome);
        $this->assertSame(304, $checker->state()->status);
        $this->assertSame('W/"e1"', $checker->state()->etag);
    }

    public function testANotModifiedResponseRestartsTheTwelveHourWindow(): void
    {
        $checker = $this->checker();
        $this->firstCheck($checker);
        $this->clock->advance(self::FRESH);
        $this->http->queue(new HttpResponse(304));
        $checker->latest();

        $this->clock->advance(self::FRESH - 1);
        $checker->latest();
        $this->assertCount(2, $this->http->requests());

        $this->clock->advance(1);
        $this->http->queue(new HttpResponse(304));
        $checker->latest();
        $this->assertCount(3, $this->http->requests());
    }

    public function testANewerReleaseReplacesTheOldOneAndItsEtag(): void
    {
        $checker = $this->checker();
        $this->firstCheck($checker);
        $this->clock->advance(self::FRESH);
        $this->http->queue($this->listing($this->releases('v1.1.0', 'v1.0.0'), 'W/"e2"'));

        $this->assertSame('1.1.0', $checker->latest()?->version);
        $this->assertSame('W/"e2"', $checker->state()->etag);
    }

    public function testAReleaseWithNoEtagMakesTheNextRequestUnconditional(): void
    {
        $checker = $this->checker();
        $this->http->queue(new HttpResponse(200, [], $this->releases('v1.0.0')));
        $checker->latest();
        $this->clock->advance(self::FRESH);
        $this->http->queue($this->listing($this->releases('v1.0.0')));

        $checker->latest();

        $this->assertArrayNotHasKey('If-None-Match', $this->http->requests()[1]['headers']);
    }

    public function testTheTokenIsSentWhenThereIsOne(): void
    {
        $this->constants->define('FDCRM_GITHUB_TOKEN', 'from-the-constant');
        $this->http->queue($this->listing($this->releases('v1.0.0')));

        $this->checker()->latest();

        $this->assertSame('Bearer from-the-constant', $this->http->requests()[0]['headers']['Authorization']);
    }

    public function testASavedTokenIsSentToo(): void
    {
        $this->options->set('fdcrm_updater_github_token', 'saved-token-value');
        $this->http->queue($this->listing($this->releases('v1.0.0')));

        $this->checker()->latest();

        $this->assertSame('Bearer saved-token-value', $this->http->requests()[0]['headers']['Authorization']);
    }

    public function testNoTokenMeansNoAuthorizationHeader(): void
    {
        $this->http->queue($this->listing($this->releases('v1.0.0')));

        $this->checker()->latest();

        $this->assertArrayNotHasKey('Authorization', $this->http->requests()[0]['headers']);
    }

    public function testTheTokenIsNeverWrittenToTheStoredState(): void
    {
        $this->constants->define('FDCRM_GITHUB_TOKEN', 'from-the-constant');
        $this->http->queue($this->listing($this->releases('v1.0.0')));

        $this->checker()->latest();

        $this->assertStringNotContainsString('from-the-constant', (string) json_encode($this->stored()));
    }

    public function testCheckNowIgnoresAFreshCacheAndSendsNoEtag(): void
    {
        $checker = $this->checker();
        $this->firstCheck($checker);
        $this->http->queue($this->listing($this->releases('v1.1.0', 'v1.0.0'), 'W/"e2"'));

        $state = $checker->checkNow();

        $this->assertCount(2, $this->http->requests());
        $this->assertArrayNotHasKey('If-None-Match', $this->http->requests()[1]['headers']);
        $this->assertSame(Outcome::OK, $state->outcome);
        $this->assertSame('1.1.0', $state->release?->version);
        $this->assertSame('W/"e2"', $state->etag);
        $this->assertSame('1.1.0', $checker->latest()?->version);
    }

    public function testClearRemovesTheStoredStateAndItsOption(): void
    {
        $checker = $this->checker();
        $this->firstCheck($checker);

        $checker->clear();

        $this->assertNull($checker->state()->release);
        $this->assertFalse($checker->state()->hasChecked());
        $this->assertSame([], $this->options->names());
    }

    public function testTheCallAfterClearSendsANewUnconditionalRequest(): void
    {
        $checker = $this->checker();
        $this->firstCheck($checker);
        $checker->clear();
        $this->http->queue($this->listing($this->releases('v1.1.0', 'v1.0.0'), 'W/"e2"'));

        $release = $checker->latest();

        $this->assertCount(2, $this->http->requests());
        $this->assertArrayNotHasKey('If-None-Match', $this->http->requests()[1]['headers']);
        $this->assertSame('1.1.0', $release?->version);
    }

    public function testClearLeavesASavedTokenInPlace(): void
    {
        $this->options->set('fdcrm_updater_github_token', 'saved-token-value');
        $checker = $this->checker();
        $this->firstCheck($checker);

        $checker->clear();

        $this->assertSame('saved-token-value', $this->options->get('fdcrm_updater_github_token'));
        $this->assertSame(['fdcrm_updater_github_token'], $this->options->names());
    }

    public function testCheckNowStillSendsTheToken(): void
    {
        $this->constants->define('FDCRM_GITHUB_TOKEN', 'from-the-constant');
        $this->http->queue($this->listing($this->releases('v1.0.0')));

        $this->checker()->checkNow();

        $this->assertSame('Bearer from-the-constant', $this->http->requests()[0]['headers']['Authorization']);
    }

    public function testCheckNowRestartsTheCacheWindow(): void
    {
        $checker = $this->checker();
        $this->firstCheck($checker);
        $this->clock->advance(self::FRESH - 10);
        $this->http->queue($this->listing($this->releases('v1.0.0')));
        $checker->checkNow();

        $this->clock->advance(self::FRESH - 1);
        $checker->latest();

        $this->assertCount(2, $this->http->requests());
    }

    public function testAFirstFailureLeavesNoReleaseAndIsRetriedAfterAnHour(): void
    {
        $checker = $this->checker();
        $this->http->queue(HttpResponse::failure('timed out'));

        $this->assertNull($checker->latest());
        $this->assertSame(Outcome::NETWORK, $checker->state()->outcome);
        $this->assertSame(0, $checker->state()->status);

        $this->clock->advance(self::HOUR - 1);
        $this->assertNull($checker->latest());
        $this->assertCount(1, $this->http->requests());

        $this->clock->advance(1);
        $this->http->queue($this->listing($this->releases('v1.0.0')));
        $this->assertSame('1.0.0', $checker->latest()?->version);
        $this->assertCount(2, $this->http->requests());
    }

    public function testANetworkFailureLeavesTheKnownReleaseAndEtagUnchanged(): void
    {
        $checker = $this->checker();
        $this->firstCheck($checker);
        $this->clock->advance(self::FRESH);
        $this->http->queue(HttpResponse::failure('timed out'));

        $release = $checker->latest();

        $this->assertSame('1.0.0', $release?->version);
        $this->assertSame(Outcome::NETWORK, $checker->state()->outcome);
        $this->assertSame('W/"e1"', $checker->state()->etag);

        $this->clock->advance(self::HOUR);
        $this->http->queue(new HttpResponse(304));
        $checker->latest();

        $this->assertSame('W/"e1"', $this->http->requests()[2]['headers']['If-None-Match']);
        $this->assertSame(Outcome::OK, $checker->state()->outcome);
    }

    public function testARateLimitLeavesTheKnownReleaseUnchangedAndTheStoredResultIsUsedForAnHour(): void
    {
        $checker = $this->checker();
        $this->firstCheck($checker);
        $this->clock->advance(self::FRESH);
        $this->http->queue(new HttpResponse(429));

        $this->assertSame('1.0.0', $checker->latest()?->version);
        $this->assertSame(Outcome::RATE_LIMITED, $checker->state()->outcome);
        $this->assertSame('W/"e1"', $checker->state()->etag);

        $this->clock->advance(self::HOUR - 1);
        $checker->latest();
        $this->assertCount(2, $this->http->requests());
    }

    public function testOutcomesThatLeaveNothingUsableRemoveTheReleaseAndEtag(): void
    {
        $responses = [
            Outcome::AUTH => new HttpResponse(401),
            Outcome::NOT_FOUND => new HttpResponse(404),
        ];

        foreach ($responses as $outcome => $response) {
            $this->setUp();
            $checker = $this->checker();
            $this->firstCheck($checker);
            $this->clock->advance(self::FRESH);
            $this->http->queue($response);

            $this->assertNull($checker->latest(), $outcome);
            $this->assertSame($outcome, $checker->state()->outcome, $outcome);
            $this->assertSame('', $checker->state()->etag, $outcome);
            $this->assertNull($checker->state()->release, $outcome);
        }
    }

    public function testOutcomesThatProvideNoInformationAboutTheReleaseLeaveItAndItsEtagUnchanged(): void
    {
        $responses = [
            'server error' => new HttpResponse(500),
            'bad gateway' => new HttpResponse(502),
            'temporary redirect' => new HttpResponse(302, ['Location' => 'https://api.github.com/repos/o/r/releases']),
            'not a list' => $this->listing('{"message":"not a list"}'),
            'not json' => $this->listing('<html>captive portal</html>'),
            'empty body' => $this->listing(''),
        ];

        foreach ($responses as $label => $response) {
            $this->setUp();
            $checker = $this->checker();
            $this->firstCheck($checker);
            $this->clock->advance(self::FRESH);
            $this->http->queue($response);

            $this->assertSame('1.0.0', $checker->latest()?->version, $label);
            $this->assertSame(Outcome::BAD_RESPONSE, $checker->state()->outcome, $label);
            $this->assertSame('W/"e1"', $checker->state()->etag, $label);

            $this->clock->advance(self::HOUR - 1);
            $checker->latest();
            $this->assertCount(2, $this->http->requests(), $label);

            $this->clock->advance(1);
            $this->http->queue(new HttpResponse(304));
            $checker->latest();
            $this->assertSame('W/"e1"', $this->http->requests()[2]['headers']['If-None-Match'], $label);
            $this->assertSame(Outcome::OK, $checker->state()->outcome, $label);
        }
    }

    public function testANewestReleaseWhoseAssetIsMissingDetailsLeavesTheKnownReleaseUnchanged(): void
    {
        $checker = $this->checker();
        $this->firstCheck($checker);
        $broken = json_decode($this->releases('v1.1.0', 'v1.0.0'), true);
        unset($broken[0]['assets'][0]['url']);
        $this->clock->advance(self::FRESH);
        $this->http->queue($this->listing((string) json_encode($broken), 'W/"e2"'));

        $this->assertSame('1.0.0', $checker->latest()?->version);
        $this->assertSame(Outcome::BAD_RESPONSE, $checker->state()->outcome);
        $this->assertSame('W/"e1"', $checker->state()->etag);
    }

    public function testAnUnreadableResponseWithNoKnownReleaseLeavesNoRelease(): void
    {
        $checker = $this->checker();
        $this->http->queue(new HttpResponse(500));

        $this->assertNull($checker->latest());
        $this->assertSame(Outcome::BAD_RESPONSE, $checker->state()->outcome);
        $this->assertSame('', $checker->state()->etag);
    }

    public function testAListWithNoStableReleaseHasNoReleaseAndIsRetriedAfterAnHour(): void
    {
        $drafts = (string) json_encode([['tag_name' => 'v1.0.0', 'draft' => true, 'prerelease' => false, 'assets' => []]]);
        $checker = $this->checker();
        $this->http->queue($this->listing($drafts));

        $this->assertNull($checker->latest());
        $this->assertSame(Outcome::NO_RELEASE, $checker->state()->outcome);
        $this->assertSame('', $checker->state()->etag);
        $this->assertSame(200, $checker->state()->status);

        $this->clock->advance(self::HOUR - 1);
        $checker->latest();
        $this->assertCount(1, $this->http->requests());

        $this->clock->advance(1);
        $this->http->queue($this->listing($this->releases('v1.0.0')));
        $this->assertSame('1.0.0', $checker->latest()?->version);
    }

    public function testANewestReleaseWithABadAssetRemovesTheOldReleaseAndNeverFallsBack(): void
    {
        $checker = $this->checker();
        $this->firstCheck($checker);
        $broken = json_decode($this->releases('v1.1.0', 'v1.0.0'), true);
        $broken[0]['assets'] = [];
        $this->clock->advance(self::FRESH);
        $this->http->queue($this->listing((string) json_encode($broken)));

        $this->assertNull($checker->latest());
        $this->assertSame(Outcome::ASSET_MISMATCH, $checker->state()->outcome);
        $this->assertNull($checker->state()->release);
        $this->assertSame('', $checker->state()->etag);
    }

    public function testAFailureAfterAMismatchDoesNotBringBackAnOldRelease(): void
    {
        $checker = $this->checker();
        $this->firstCheck($checker);
        $this->clock->advance(self::FRESH);
        $this->http->queue($this->listing('[]'));
        $checker->latest();
        $this->clock->advance(self::HOUR);
        $this->http->queue(HttpResponse::failure('timed out'));

        $this->assertNull($checker->latest());
    }

    public function testANotModifiedResponseWithNoStoredReleaseIsABadResponse(): void
    {
        $checker = $this->checker();
        $this->http->queue(new HttpResponse(304));

        $this->assertNull($checker->latest());
        $this->assertSame(Outcome::BAD_RESPONSE, $checker->state()->outcome);
        $this->assertArrayNotHasKey('If-None-Match', $this->http->requests()[0]['headers']);
    }

    public function testAnEtagIsNotSentWhenTheStoredStateHasNoReleaseToConfirm(): void
    {
        $this->options->set(self::OPTION, (new ReleaseState(self::NOW - self::HOUR, Outcome::AUTH, 401, 'W/"stale"'))->toArray());
        $this->http->queue($this->listing($this->releases('v1.0.0')));

        $this->assertSame('1.0.0', $this->checker()->latest()?->version);
        $this->assertArrayNotHasKey('If-None-Match', $this->http->requests()[0]['headers']);
    }

    public function testAStateThatCannotBeReadIsTreatedAsNoCheckAtAll(): void
    {
        $this->options->set(self::OPTION, ['v' => 99, 'from' => 'a newer version']);
        $this->http->queue($this->listing($this->releases('v1.0.0')));

        $this->assertSame('1.0.0', $this->checker()->latest()?->version);
        $this->assertCount(1, $this->http->requests());
        $this->assertArrayNotHasKey('If-None-Match', $this->http->requests()[0]['headers']);
        $this->assertSame(1, $this->stored()['v']);
    }

    public function testStateReadsTheStoredStateWithoutSendingARequest(): void
    {
        $checker = $this->checker();

        $this->assertSame(ReleaseState::empty()->toArray(), $checker->state()->toArray());
        $this->assertSame([], $this->http->requests());

        $this->firstCheck($checker);
        $before = count($this->http->requests());
        $checker->state();

        $this->assertCount($before, $this->http->requests());
    }

    public function testAStoredFreshStateIsUsedByAnotherCheckerWithoutARequest(): void
    {
        $this->firstCheck($this->checker());

        $other = $this->checker();

        $this->assertSame('1.0.0', $other->latest()?->version);
        $this->assertCount(1, $this->http->requests());
    }

    public function testAFreshFailureIsNotRetriedByLatestButIsByCheckNow(): void
    {
        $checker = $this->checker();
        $this->http->queue(new HttpResponse(401));
        $checker->latest();

        $checker->latest();
        $this->assertCount(1, $this->http->requests());

        $this->http->queue($this->listing($this->releases('v1.0.0')));
        $state = $checker->checkNow();

        $this->assertCount(2, $this->http->requests());
        $this->assertSame(Outcome::OK, $state->outcome);
    }
}
