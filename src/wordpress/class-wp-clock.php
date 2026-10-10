<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\WordPress;

use ScottOffen\WpGithubUpdater\Platform\Clock;

/**
 * Tells the time from the server clock.
 */
final class WpClock implements Clock
{
    public function now(): int
    {
        return time();
    }
}
