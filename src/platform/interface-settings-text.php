<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Platform;

/**
 * @internal
 */
interface SettingsText
{

    public function tokenLabel(): string;


    public function tokenHelp(): string;

    /**
     * @param string $constant The name of the constant.
     */
    public function constantHelp(string $constant): string;


    public function saveButton(): string;


    public function removeButton(): string;


    public function checkButton(): string;


    public function statusLabel(): string;


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


    public function noneFound(): string;

    /**
     * @param string $outcome An `Outcome` code. A code that has no specific
     *     sentence gets a general one.
     */
    public function problem(string $outcome): string;

    /**
     * @param string $code A `Notice` code.
     *
     * @return string The text, or an empty string for a code that isn't a notice.
     */
    public function notice(string $code): string;
}
