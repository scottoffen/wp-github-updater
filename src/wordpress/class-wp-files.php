<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\WordPress;

use ScottOffen\WpGithubUpdater\Internal\Platform\Files;

/**
 * @internal
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
