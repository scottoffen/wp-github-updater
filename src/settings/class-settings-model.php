<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Settings;

use ScottOffen\WpGithubUpdater\Internal\Update\ReleaseState;

/**
 * The values for the view of the settings section.
 *
 * @internal
 */
final class SettingsModel
{
    /**
     * @param string $heading The heading of the section. An empty string means
     *     no heading.
     * @param ReleaseState $state The state of an update check.
     * @param string $installedVersion The installed version, or an empty string
     *     when there is none.
     * @param string $whenChecked The time that the check ended, formatted, or
     *     an empty string when no check has run.
     * @param bool $locked True when the token constant is in use.
     * @param string $constantName The name of the token constant.
     * @param string $maskedToken The masked form of the saved token, or an
     *     empty string. The model has no other form of the token.
     * @param string $notice A `Notice` code, or an empty string for none.
     * @param string $tokenField The name and the ID of the token field.
     * @param SettingsForm $saveForm The form for saving the token.
     * @param SettingsForm $removeForm The form for removing the token.
     * @param SettingsForm $checkForm The form for checking for updates.
     */
    public function __construct(
        public readonly string $heading,
        public readonly ReleaseState $state,
        public readonly string $installedVersion,
        public readonly string $whenChecked,
        public readonly bool $locked,
        public readonly string $constantName,
        public readonly string $maskedToken,
        public readonly string $notice,
        public readonly string $tokenField,
        public readonly SettingsForm $saveForm,
        public readonly SettingsForm $removeForm,
        public readonly SettingsForm $checkForm,
    ) {
    }
}
