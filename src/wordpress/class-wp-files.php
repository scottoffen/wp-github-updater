<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\WordPress;

use ScottOffen\WpGithubUpdater\Platform\Files;

/**
 * Reads, lists, hashes, and deletes files on the server's file system.
 *
 * Temporary files come from `wp_tempnam`, so they sit in the WordPress
 * temporary directory, and deletes go through `wp_delete_file`, so a site's
 * filters see them.
 */
final class WpFiles implements Files
{
    public function readHead(string $path, int $bytes = 8192): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $head = file_get_contents($path, false, null, 0, max(0, $bytes));

        return $head === false ? null : $head;
    }

    public function children(string $path): ?array
    {
        if (!is_dir($path) || !is_readable($path)) {
            return null;
        }

        $names = scandir($path);

        if ($names === false) {
            return null;
        }

        return array_values(array_diff($names, ['.', '..']));
    }

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

    public function hash(string $path, string $algorithm): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $hash = hash_file($algorithm, $path);

        return $hash === false ? null : $hash;
    }

    public function delete(string $path): void
    {
        \wp_delete_file($path);
    }
}
