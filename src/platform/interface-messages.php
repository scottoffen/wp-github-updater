<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Platform;

/**
 * The text the library shows to people, translated by the platform.
 *
 * Messages are written for the site's administrators, who may not know where
 * the updates come from, so they don't name the update source.
 */
interface Messages
{
    /**
     * Builds the error to hand back to the platform for an outcome.
     *
     * @param string $outcome An `Outcome` code. A code with no message of its
     *     own gets a general one.
     *
     * @return object The platform's error object, which is a `WP_Error` on WordPress.
     */
    public function error(string $outcome): object;

    /**
     * The text for an outcome, for a place that shows a sentence and not an error.
     *
     * @param string $outcome An `Outcome` code. A code with no message of its
     *     own gets a general one.
     */
    public function message(string $outcome): string;

    /**
     * The sentence shown in place of release notes when a release has none.
     */
    public function noNotes(): string;
}
