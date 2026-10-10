<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\WordPress;

use ScottOffen\WpGithubUpdater\Platform\Messages;
use ScottOffen\WpGithubUpdater\Release\Outcome;
use WP_Error;

use function __;

/**
 * The library's text, translated with the `wp-github-updater` text domain.
 *
 * Every string is a literal in a direct call to `__()`, so translation tools
 * can find it. No translation files ship yet, so WordPress shows the English
 * text. An error's code is `wp_github_updater_` followed by the outcome code.
 */
final class WpMessages implements Messages
{
    public function error(string $outcome): object
    {
        return new WP_Error('wp_github_updater_' . $outcome, $this->message($outcome));
    }

    public function noNotes(): string
    {
        return __('No release notes were published for this version.', 'wp-github-updater');
    }

    public function message(string $outcome): string
    {
        return match ($outcome) {
            Outcome::AUTH => __('The update could not be downloaded because access was refused. Check the access token in the settings for this plugin or theme.', 'wp-github-updater'),
            Outcome::NOT_FOUND => __('The update could not be found. If the update source is private, check the access token in the settings for this plugin or theme.', 'wp-github-updater'),
            Outcome::RATE_LIMITED => __('The update server is limiting requests right now. Try again in a little while.', 'wp-github-updater'),
            Outcome::NETWORK => __('The update server could not be reached. Try again in a little while.', 'wp-github-updater'),
            Outcome::BAD_RESPONSE => __('The update server sent an answer that could not be used. Try again in a little while.', 'wp-github-updater'),
            Outcome::DIGEST_MISMATCH => __('The downloaded update did not match its checksum, so it was not installed. Try again.', 'wp-github-updater'),
            Outcome::FILE_ERROR => __('The update could not be saved to a temporary file. Check that the temporary directory on the server can be written to.', 'wp-github-updater'),
            Outcome::REFUSED => __('The address of the update was not accepted, so nothing was downloaded.', 'wp-github-updater'),
            Outcome::FOLDER_MISMATCH => __('The update package is not in the right folder, so it was not installed.', 'wp-github-updater'),
            Outcome::NO_RELEASE => __('No release information is available yet.', 'wp-github-updater'),
            default => __('The update could not be completed.', 'wp-github-updater'),
        };
    }
}
