<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\WordPress;

use ScottOffen\WpGithubUpdater\Internal\Platform\Messages;
use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;
use WP_Error;

use function __;

/**
 * The implementation of the `Messages` interface for the `WP_Error` class and
 * the `__()` function.
 *
 * @internal
 */
final class WpMessages implements Messages
{
    /**
     * Returns a `WP_Error` object for an outcome.
     *
     * @return object A `WP_Error` object with the code `wp_github_updater_`
     *     followed by the outcome, and the message from the `message()` method.
     */
    public function error(string $outcome): object
    {
        return new WP_Error('wp_github_updater_' . $outcome, $this->message($outcome));
    }

    /**
     * Returns the translated message for an outcome.
     *
     * The translation uses the `__()` function with the `wp-github-updater`
     * text domain.
     *
     * @return string The message for the outcome, or the message "The update
     *     could not be completed." for `OK`, `NOT_MODIFIED`, `ASSET_MISMATCH`,
     *     and any other code.
     */
    public function message(string $outcome): string
    {
        return match ($outcome) {
            Outcome::AUTH => __('The update could not be downloaded because access was refused. Check the access token in the settings for this plugin or theme.', 'wp-github-updater'),
            Outcome::NOT_FOUND => __('The update could not be found. If the update source is private, check the access token in the settings for this plugin or theme.', 'wp-github-updater'),
            Outcome::RATE_LIMITED => __('The update server is limiting requests right now. Try again in a little while.', 'wp-github-updater'),
            Outcome::NETWORK => __('The update server could not be reached. Try again in a little while.', 'wp-github-updater'),
            Outcome::BAD_RESPONSE => __('The update server sent a response that could not be used. Try again in a little while.', 'wp-github-updater'),
            Outcome::DIGEST_MISMATCH => __('The downloaded update did not match its checksum, so it was not installed. Try again.', 'wp-github-updater'),
            Outcome::FILE_ERROR => __('The update could not be saved to a temporary file. Check that the temporary directory on the server can be written to.', 'wp-github-updater'),
            Outcome::REFUSED => __('The address of the update was not accepted, so nothing was downloaded.', 'wp-github-updater'),
            Outcome::FOLDER_MISMATCH => __('The update package is not in the right folder, so it was not installed.', 'wp-github-updater'),
            Outcome::NO_RELEASE => __('No release information is available yet.', 'wp-github-updater'),
            default => __('The update could not be completed.', 'wp-github-updater'),
        };
    }

    /**
     * Returns the translated message for a release without notes.
     *
     * @return string The message "No release notes were published for this
     *     version."
     */
    public function noNotes(): string
    {
        return __('No release notes were published for this version.', 'wp-github-updater');
    }
}
