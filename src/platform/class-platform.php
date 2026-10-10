<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Platform;

use ScottOffen\WpGithubUpdater\Internal\Http\HttpClient;

/**
 * Everything that the library uses from the platform that it runs on, gathered
 * in one place.
 *
 * The builder uses it as a whole and passes each class only the collaborators
 * that the class uses, so the classes have narrow constructors and stay easy to
 * test.
 *
 * @internal
 */
final class Platform
{
    public function __construct(
        public readonly Clock $clock,
        public readonly OptionStore $options,
        public readonly Constants $constants,
        public readonly Files $files,
        public readonly Hooks $hooks,
        public readonly HttpClient $http,
        public readonly HtmlSanitizer $htmlSanitizer,
        public readonly UpdateData $updateData,
        public readonly Messages $messages,
        public readonly Admin $admin,
        public readonly SettingsText $settingsText,
    ) {
    }
}
