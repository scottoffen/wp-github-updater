<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\WordPress;

use ScottOffen\WpGithubUpdater\Internal\Platform\Clock;

/**
 * @internal
 */
final class WpClock implements Clock
{
    public function now(): int
    {
        return time();
    }
}
