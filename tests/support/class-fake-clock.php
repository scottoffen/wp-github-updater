<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use ScottOffen\WpGithubUpdater\Internal\Platform\Clock;


final class FakeClock implements Clock
{
    public function __construct(private int $now = 1_800_000_000)
    {
    }

    public function now(): int
    {
        return $this->now;
    }


    public function set(int $now): void
    {
        $this->now = $now;
    }


    public function advance(int $seconds): void
    {
        $this->now += $seconds;
    }
}
