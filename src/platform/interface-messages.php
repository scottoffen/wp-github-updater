<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Platform;

/**
 * @internal
 */
interface Messages
{
    /**
     * @param string $outcome An `Outcome` code. A code that has no specific
     *     message gets a general one.
     *
     * @return object The platform's error object, which is a `WP_Error` on
     *     WordPress.
     */
    public function error(string $outcome): object;

    /**
     * @param string $outcome An `Outcome` code. A code that has no specific
     *     message gets a general one.
     */
    public function message(string $outcome): string;


    public function noNotes(): string;
}
