<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\WordPress;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Internal\WordPress\WpClock;

final class WpClockTest extends TestCase
{
    public function testItReportsTheServerTime(): void
    {
        $before = time();
        $now = (new WpClock())->now();
        $after = time();

        $this->assertGreaterThanOrEqual($before, $now);
        $this->assertLessThanOrEqual($after, $now);
    }
}
