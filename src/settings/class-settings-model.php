<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Settings;

use ScottOffen\WpGithubUpdater\Internal\Update\ReleaseState;

/**
 * @internal
 */
final class SettingsModel
{
    /**
     * @param string $heading The heading of the section, or an empty string for
     *     none.
     * @param ReleaseState $state The state after the last check.
     * @param string $installedVersion The installed version, or an empty string
     *     when it is unknown.
     * @param string $whenChecked The time of the last check, formatted. Empty
     *     before any check.
     * @param bool $locked Whether the token comes from a constant.
     * @param string $constantName The name of the constant where the token can
     *     be defined.
     * @param string $maskedToken The saved token, masked. Empty when none is
     *     saved or the token comes from a constant. It is the only form of the
     *     token that the view ever sees.
     * @param string $notice A `Notice` code to show, or an empty string for
     *     none.
     * @param string $tokenField The name and id of the token field.
     * @param SettingsForm $saveForm The form that saves the token.
     * @param SettingsForm $removeForm The form that removes the token.
     * @param SettingsForm $checkForm The form that checks for updates.
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
