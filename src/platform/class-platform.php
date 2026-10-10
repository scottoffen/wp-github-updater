<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Platform;

use ScottOffen\WpGithubUpdater\Internal\Http\HttpClient;

/**
 * The platform dependencies of the library: the clock, the option store, the
 * constants, the files, the hooks, the HTTP client, the HTML sanitizer, the
 * update data, the messages, the admin functions, and the settings text.
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
