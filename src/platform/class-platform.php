<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Platform;

use ScottOffen\WpGithubUpdater\Http\HttpClient;

/**
 * Everything the library needs from the platform it runs on, held in one place.
 *
 * The builder is the only code that takes this apart. It hands each class the
 * few collaborators that class uses, so the classes keep narrow constructors
 * and stay easy to test.
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
