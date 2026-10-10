<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use ScottOffen\WpGithubUpdater\Internal\Platform\Clock;

/**
 * A fake `Clock` for returning a time that a test sets.
 */
final class FakeClock implements Clock
{
    /**
     * @param int $now The starting time as a Unix timestamp.
     */
    public function __construct(private int $now = 1_800_000_000)
    {
    }

    /**
     * Adds seconds to the current time.
     */
    public function advance(int $seconds): void
    {
        $this->now += $seconds;
    }

    public function now(): int
    {
        return $this->now;
    }

    /**
     * Sets the current time.
     */
    public function set(int $now): void
    {
        $this->now = $now;
    }
}
