<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\WordPress;

use ScottOffen\WpGithubUpdater\Internal\Platform\Files;

/**
 * The implementation of the `Files` interface for the `file_get_contents()`,
 * `hash_file()`, `wp_tempnam()`, and `wp_delete_file()` functions.
 *
 * @internal
 */
final class WpFiles implements Files
{
    public function delete(string $path): void
    {
        \wp_delete_file($path);
    }

    public function hash(string $path, string $algorithm): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $hash = hash_file($algorithm, $path);

        return $hash === false ? null : $hash;
    }

    /**
     * Returns the first bytes of a file, read with the `file_get_contents()`
     * function.
     *
     * A `$bytes` value below 0 is treated as 0.
     *
     * @return string|null The bytes read, or null when the path isn't a
     *     readable file or the `file_get_contents()` function returns `false`.
     */
    public function readHead(string $path, int $bytes = 8192): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $head = file_get_contents($path, false, null, 0, max(0, $bytes));

        return $head === false ? null : $head;
    }

    /**
     * Creates the file with the `wp_tempnam()` function and the name
     * `wp-github-updater.zip`.
     *
     * When the `wp_tempnam()` function doesn't exist, the method loads
     * `wp-admin/includes/file.php` first, and returns null when `ABSPATH`
     * isn't defined.
     *
     * @return string|null The path of the new file, or null when the
     *     `wp_tempnam()` function isn't available or doesn't return a path.
     */
    public function tempFile(): ?string
    {
        if (!function_exists('wp_tempnam')) {
            if (!defined('ABSPATH')) {
                return null;
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $path = \wp_tempnam('wp-github-updater.zip');

        return is_string($path) && $path !== '' ? $path : null;
    }
}
