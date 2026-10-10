<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Platform;

/**
 * The messages for the outcomes of an update and for a release without notes.
 *
 * @internal
 */
interface Messages
{
    /**
     * Returns an error for an outcome.
     *
     * @param string $outcome An `Outcome` code. A code that has no specific
     *     message gets a general message.
     *
     * @return object An error object that has the message for the outcome.
     */
    public function error(string $outcome): object;

    /**
     * Returns the message for an outcome.
     *
     * @param string $outcome An `Outcome` code. A code that has no specific
     *     message gets a general message.
     *
     * @return string The message as text.
     */
    public function message(string $outcome): string;

    /**
     * Returns the message for a release without notes.
     *
     * @return string The message as text.
     */
    public function noNotes(): string;
}
