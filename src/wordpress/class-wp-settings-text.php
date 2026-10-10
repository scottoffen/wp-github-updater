<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\WordPress;

use ScottOffen\WpGithubUpdater\Platform\SettingsText;
use ScottOffen\WpGithubUpdater\Release\Outcome;
use ScottOffen\WpGithubUpdater\Settings\Notice;

use function __;

/**
 * The text of the settings section, translated with the `wp-github-updater`
 * text domain.
 *
 * Every string is a literal in a direct call to `__()`, so translation tools
 * can find it. No translation files ship yet, so WordPress shows the English
 * text.
 */
final class WpSettingsText implements SettingsText
{
    public function tokenLabel(): string
    {
        return __('Access token', 'wp-github-updater');
    }

    public function tokenHelp(): string
    {
        return __('Only needed when the updates are not publicly available. Leave this empty otherwise.', 'wp-github-updater');
    }

    public function constantHelp(string $constant): string
    {
        /* translators: %s: the name of a PHP constant. */
        return sprintf(__('This token is set by the %s constant, so it cannot be changed here.', 'wp-github-updater'), $constant);
    }

    public function saveButton(): string
    {
        return __('Save token', 'wp-github-updater');
    }

    public function removeButton(): string
    {
        return __('Remove token', 'wp-github-updater');
    }

    public function checkButton(): string
    {
        return __('Check for updates now', 'wp-github-updater');
    }

    public function statusLabel(): string
    {
        return __('Status', 'wp-github-updater');
    }

    public function neverChecked(): string
    {
        return __('Not checked yet.', 'wp-github-updater');
    }

    public function lastChecked(string $when): string
    {
        /* translators: %s: a date and time. */
        return sprintf(__('Last checked: %s.', 'wp-github-updater'), $when);
    }

    public function installed(string $version): string
    {
        /* translators: %s: a version number. */
        return sprintf(__('Installed version: %s.', 'wp-github-updater'), $version);
    }

    public function latest(string $version): string
    {
        /* translators: %s: a version number. */
        return sprintf(__('Latest version found: %s.', 'wp-github-updater'), $version);
    }

    public function noneFound(): string
    {
        return __('No release found.', 'wp-github-updater');
    }

    public function problem(string $outcome): string
    {
        return match ($outcome) {
            Outcome::AUTH => __('Access was refused. Check the access token.', 'wp-github-updater'),
            Outcome::NOT_FOUND => __('The updates could not be found. If they are private, check the access token.', 'wp-github-updater'),
            Outcome::RATE_LIMITED => __('The update server is limiting requests right now. It will try again soon.', 'wp-github-updater'),
            Outcome::NETWORK => __('The update server could not be reached. It will try again soon.', 'wp-github-updater'),
            Outcome::BAD_RESPONSE => __('The update server sent an answer that could not be used. It will try again soon.', 'wp-github-updater'),
            Outcome::NO_RELEASE => __('No published release was found.', 'wp-github-updater'),
            Outcome::ASSET_MISMATCH => __('The newest release is not set up correctly, so it cannot be installed.', 'wp-github-updater'),
            default => __('The last check did not succeed.', 'wp-github-updater'),
        };
    }

    public function notice(string $code): string
    {
        return match ($code) {
            Notice::SAVED => __('The access token was saved and the updates were checked.', 'wp-github-updater'),
            Notice::REMOVED => __('The access token was removed and the updates were checked.', 'wp-github-updater'),
            Notice::CHECKED => __('The updates were checked.', 'wp-github-updater'),
            Notice::EMPTY => __('Enter an access token to save it.', 'wp-github-updater'),
            Notice::INVALID => __('That access token cannot be used. Check that it was copied exactly, with no spaces.', 'wp-github-updater'),
            Notice::LOCKED => __('The access token is set in the site configuration and cannot be changed here.', 'wp-github-updater'),
            Notice::EXPIRED => __('The form expired. Please try again.', 'wp-github-updater'),
            default => '',
        };
    }
}
