<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Platform;

/**
 * The text of the settings section, translated by the platform.
 *
 * Like the other messages, it doesn't name where the updates come from.
 * Every method returns plain text, and the view escapes it.
 */
interface SettingsText
{
    /** The label of the token field. */
    public function tokenLabel(): string;

    /** The help under the token field when the person can edit it. */
    public function tokenHelp(): string;

    /**
     * The help under the token field when a constant supplies the token.
     *
     * @param string $constant The name of the constant.
     */
    public function constantHelp(string $constant): string;

    /** The label of the button that saves the token. */
    public function saveButton(): string;

    /** The label of the button that removes the token. */
    public function removeButton(): string;

    /** The label of the button that checks for updates. */
    public function checkButton(): string;

    /** The label of the status line. */
    public function statusLabel(): string;

    /** The status before any check has run. */
    public function neverChecked(): string;

    /**
     * @param string $when The time of the last check, already formatted.
     */
    public function lastChecked(string $when): string;

    /**
     * @param string $version The installed version.
     */
    public function installed(string $version): string;

    /**
     * @param string $version The latest version found.
     */
    public function latest(string $version): string;

    /** The status when there is no release to report. */
    public function noneFound(): string;

    /**
     * A sentence about why the last check didn't succeed.
     *
     * @param string $outcome An `Outcome` code. A code with no sentence of its
     *     own gets a general one.
     */
    public function problem(string $outcome): string;

    /**
     * The text of a notice.
     *
     * @param string $code A `Notice` code.
     *
     * @return string The text, or an empty string for a code that isn't a notice.
     */
    public function notice(string $code): string;
}
