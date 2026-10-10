<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Platform;

/**
 * @internal
 */
interface Clock
{
    /**
     * @return int The current time as a Unix timestamp, in seconds.
     */
    public function now(): int;
}
