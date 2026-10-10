<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Update;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;
use ScottOffen\WpGithubUpdater\Internal\Release\Release;
use ScottOffen\WpGithubUpdater\Internal\Update\ReleaseCache;
use ScottOffen\WpGithubUpdater\Internal\Update\ReleaseState;
use ScottOffen\WpGithubUpdater\Tests\Support\FakeClock;
use ScottOffen\WpGithubUpdater\Tests\Support\FakeOptionStore;

final class ReleaseCacheTest extends TestCase
{
    private const NOW = 1_800_000_000;

    private FakeOptionStore $options;

    private FakeClock $clock;

    protected function setUp(): void
    {
        $this->options = new FakeOptionStore();
        $this->clock = new FakeClock(self::NOW);
    }

    private function cache(string $prefix = 'fdcrm'): ReleaseCache
    {
        return new ReleaseCache($this->options, $this->clock, $prefix);
    }

    private function release(): Release
    {
        return new Release('v1.0.0', '1.0.0', '', '', '', 1, 'my-plugin-1.0.0.zip', 'https://api.github.com/repos/o/r/releases/assets/1', 10, '');
    }

    public function testTheOptionNameComesFromThePrefix(): void
    {
        $this->assertSame('fdcrm_updater_release_state', $this->cache('fdcrm')->optionName());
        $this->assertSame('my_theme_updater_release_state', $this->cache('my_theme')->optionName());
    }

    public function testAPrefixThatIsNotLowercaseLettersDigitsAndUnderscoresIsRejected(): void
    {
        foreach (['', 'FDCRM', '1abc', '_abc', 'my-theme', 'a b', "abc\n"] as $prefix) {
            try {
                $this->cache($prefix);
                $this->fail('Accepted: ' . var_export($prefix, true));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testNothingStoredGivesTheEmptyState(): void
    {
        $this->assertFalse($this->cache()->state()->hasChecked());
        $this->assertNull($this->cache()->state()->release);
    }

    public function testAStateThatCannotBeReadGivesTheEmptyState(): void
    {
        foreach (['text', 42, ['v' => 99], ['v' => 1], false] as $value) {
            $this->options->set('fdcrm_updater_release_state', $value);

            $this->assertFalse($this->cache()->state()->hasChecked(), var_export($value, true));
        }
    }

    public function testASavedStateComesBackAndNeverAutoloads(): void
    {
        $cache = $this->cache();
        $state = new ReleaseState(self::NOW, Outcome::OK, 200, 'W/"abc"', $this->release());

        $cache->save($state);

        $this->assertSame($state->toArray(), $cache->state()->toArray());
        $this->assertFalse($this->options->autoloads('fdcrm_updater_release_state'));
        $this->assertSame(['fdcrm_updater_release_state'], $this->options->names());
    }

    public function testStoredStateContainsOnlyPlainArrays(): void
    {
        $this->cache()->save(new ReleaseState(self::NOW, Outcome::OK, 200, 'W/"abc"', $this->release()));

        $stored = $this->options->get('fdcrm_updater_release_state');

        $this->assertIsArray($stored);
        $this->assertIsArray($stored['release']);
    }

    public function testClearingRemovesTheState(): void
    {
        $cache = $this->cache();
        $cache->save(new ReleaseState(self::NOW, Outcome::OK, 200));

        $cache->clear();

        $this->assertFalse($this->options->has('fdcrm_updater_release_state'));
        $this->assertFalse($cache->state()->hasChecked());
    }

    public function testNothingIsFreshBeforeAnyCheck(): void
    {
        $this->assertFalse($this->cache()->isFresh());
    }

    public function testAStateThatEndedWithAReleaseIsFreshForTwelveHours(): void
    {
        $cache = $this->cache();
        $cache->save(new ReleaseState(self::NOW, Outcome::OK, 200, '', $this->release()));

        $this->assertTrue($cache->isFresh());

        $this->clock->advance(ReleaseCache::FRESH_SECONDS - 1);
        $this->assertTrue($cache->isFresh());

        $this->clock->advance(1);
        $this->assertFalse($cache->isFresh());
    }

    public function testAStateThatEndedAnyOtherWayIsFreshForOneHour(): void
    {
        foreach ([Outcome::NETWORK, Outcome::AUTH, Outcome::NO_RELEASE, Outcome::ASSET_MISMATCH, Outcome::RATE_LIMITED, 'unknown_code'] as $outcome) {
            $this->clock->set(self::NOW);
            $cache = $this->cache();
            $cache->save(new ReleaseState(self::NOW, $outcome, 0));

            $this->clock->advance(ReleaseCache::RETRY_SECONDS - 1);
            $this->assertTrue($cache->isFresh(), $outcome);

            $this->clock->advance(1);
            $this->assertFalse($cache->isFresh(), $outcome);
        }
    }

    public function testTheWindowsAreTwelveHoursAndOneHour(): void
    {
        $this->assertSame(12 * 60 * 60, ReleaseCache::FRESH_SECONDS);
        $this->assertSame(60 * 60, ReleaseCache::RETRY_SECONDS);
    }

    public function testACheckTimeInTheFutureIsNeverFresh(): void
    {
        $cache = $this->cache();
        $cache->save(new ReleaseState(self::NOW + 1, Outcome::OK, 200, '', $this->release()));

        $this->assertFalse($cache->isFresh());
    }

    public function testTwoPrefixesNeverShareAState(): void
    {
        $first = $this->cache('first');
        $second = $this->cache('second');

        $first->save(new ReleaseState(self::NOW, Outcome::OK, 200, '', $this->release()));

        $this->assertTrue($first->isFresh());
        $this->assertFalse($second->isFresh());
        $this->assertNull($second->state()->release);
    }
}
