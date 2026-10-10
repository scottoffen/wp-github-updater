<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Platform;

/**
 * The texts for the settings section: the labels, the help texts, the buttons,
 * the status sentences, and the notices.
 *
 * @internal
 */
interface SettingsText
{
    /**
     * Returns the label of the button for checking for updates.
     *
     * @return string The label.
     */
    public function checkButton(): string;

    /**
     * Returns the help text for the token field when the token constant is in
     * use.
     *
     * @param string $constant The name of the token constant.
     *
     * @return string The help text, which includes the name of the constant.
     */
    public function constantHelp(string $constant): string;

    /**
     * Returns the sentence for the installed version.
     *
     * @param string $version The installed version.
     *
     * @return string The sentence, which includes the version.
     */
    public function installed(string $version): string;

    /**
     * Returns the sentence for the time that a check ended.
     *
     * @param string $when The time that the check ended, already formatted.
     *
     * @return string The sentence, which includes the time.
     */
    public function lastChecked(string $when): string;

    /**
     * Returns the sentence for the version of the release.
     *
     * @param string $version The version of the release.
     *
     * @return string The sentence, which includes the version.
     */
    public function latest(string $version): string;

    /**
     * Returns the sentence for when no check has run.
     *
     * @return string The sentence.
     */
    public function neverChecked(): string;

    /**
     * Returns the sentence for when no release is found.
     *
     * @return string The sentence.
     */
    public function noneFound(): string;

    /**
     * Returns the text of a notice.
     *
     * @param string $code A `Notice` code.
     *
     * @return string The text, or an empty string for a code that isn't a
     *     notice code.
     */
    public function notice(string $code): string;

    /**
     * Returns the sentence for the problem with an outcome.
     *
     * @param string $outcome An `Outcome` code. A code that has no specific
     *     sentence gets a general sentence.
     *
     * @return string The sentence.
     */
    public function problem(string $outcome): string;

    /**
     * Returns the label of the button for removing the token.
     *
     * @return string The label.
     */
    public function removeButton(): string;

    /**
     * Returns the label of the button for saving the token.
     *
     * @return string The label.
     */
    public function saveButton(): string;

    /**
     * Returns the label of the status row.
     *
     * @return string The label.
     */
    public function statusLabel(): string;

    /**
     * Returns the help text for the token field.
     *
     * @return string The help text.
     */
    public function tokenHelp(): string;

    /**
     * Returns the label of the token field.
     *
     * @return string The label.
     */
    public function tokenLabel(): string;
}
