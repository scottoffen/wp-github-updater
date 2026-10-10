<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Platform;

/**
 * A clock for getting the current time.
 *
 * @internal
 */
interface Clock
{
    /**
     * Returns the current time.
     *
     * @return int The current time as a Unix timestamp, in seconds.
     */
    public function now(): int;
}
