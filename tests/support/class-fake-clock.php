<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use ScottOffen\WpGithubUpdater\Platform\Clock;

/**
 * A clock that only moves when the test moves it.
 */
final class FakeClock implements Clock
{
    public function __construct(private int $now = 1_800_000_000)
    {
    }

    public function now(): int
    {
        return $this->now;
    }

    /** Sets the time to a given Unix timestamp. */
    public function set(int $now): void
    {
        $this->now = $now;
    }

    /** Moves the time forward by a number of seconds. */
    public function advance(int $seconds): void
    {
        $this->now += $seconds;
    }
}
