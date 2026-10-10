<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\WordPress;

use ScottOffen\WpGithubUpdater\Internal\Platform\SettingsText;
use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;
use ScottOffen\WpGithubUpdater\Internal\Settings\Notice;

use function __;

/**
 * The implementation of the `SettingsText` interface for the `__()` function
 * with the `wp-github-updater` text domain.
 *
 * @internal
 */
final class WpSettingsText implements SettingsText
{
    public function checkButton(): string
    {
        return __('Check for updates now', 'wp-github-updater');
    }

    public function constantHelp(string $constant): string
    {
        /* translators: %s: the name of a PHP constant. */
        return sprintf(__('This token is set by the %s constant, so it cannot be changed here.', 'wp-github-updater'), $constant);
    }

    public function installed(string $version): string
    {
        /* translators: %s: a version number. */
        return sprintf(__('Installed version: %s.', 'wp-github-updater'), $version);
    }

    public function lastChecked(string $when): string
    {
        /* translators: %s: a date and time. */
        return sprintf(__('Last checked: %s.', 'wp-github-updater'), $when);
    }

    public function latest(string $version): string
    {
        /* translators: %s: a version number. */
        return sprintf(__('Latest version found: %s.', 'wp-github-updater'), $version);
    }

    public function neverChecked(): string
    {
        return __('Not checked yet.', 'wp-github-updater');
    }

    public function noneFound(): string
    {
        return __('No release found.', 'wp-github-updater');
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

    /**
     * Returns the translated sentence for the problem with an outcome.
     *
     * @return string The sentence for `AUTH`, `NOT_FOUND`, `RATE_LIMITED`,
     *     `NETWORK`, `BAD_RESPONSE`, or `ASSET_MISMATCH`, or the sentence "The
     *     last check did not succeed." for any other code.
     */
    public function problem(string $outcome): string
    {
        return match ($outcome) {
            Outcome::AUTH => __('Access was refused. Check the access token.', 'wp-github-updater'),
            Outcome::NOT_FOUND => __('The updates could not be found. If they are private, check the access token.', 'wp-github-updater'),
            Outcome::RATE_LIMITED => __('The update server is limiting requests right now.', 'wp-github-updater'),
            Outcome::NETWORK => __('The update server could not be reached.', 'wp-github-updater'),
            Outcome::BAD_RESPONSE => __('The update server sent a response that could not be used.', 'wp-github-updater'),
            Outcome::ASSET_MISMATCH => __('The newest release is not set up correctly, so it cannot be installed.', 'wp-github-updater'),
            default => __('The last check did not succeed.', 'wp-github-updater'),
        };
    }

    public function removeButton(): string
    {
        return __('Remove token', 'wp-github-updater');
    }

    public function saveButton(): string
    {
        return __('Save token', 'wp-github-updater');
    }

    public function statusLabel(): string
    {
        return __('Status', 'wp-github-updater');
    }

    public function tokenHelp(): string
    {
        return __('Required for a private repository. Optional for a public repository, where a token raises the limit on requests to the update server.', 'wp-github-updater');
    }

    public function tokenLabel(): string
    {
        return __('Access token', 'wp-github-updater');
    }
}
