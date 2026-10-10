<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Platform;

/**
 * Tells the library what time it is, so tests can move time on their own.
 */
interface Clock
{
    /**
     * @return int The current time as a Unix timestamp, in seconds.
     */
    public function now(): int;
}
