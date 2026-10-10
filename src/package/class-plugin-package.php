<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Package;

use InvalidArgumentException;

/**
 * Who a plugin is: its main file, its slug, and the key WordPress knows it by.
 *
 * The slug is the name of the folder the plugin is installed in. WordPress
 * installs an update into the folder named inside the update package, so the
 * library depends on the slug being a real folder. A plugin that is a single
 * file directly in the plugins folder has no folder of its own, so it can't
 * be updated this way.
 */
final class PluginPackage
{
    private function __construct(
        public readonly string $file,
        public readonly string $slug,
        public readonly string $key,
    ) {
    }

    /**
     * @param string $file The path of the plugin's main file, which is `__FILE__` there.
     * @param string|null $pluginDir The path of the plugins folder. When given,
     *     a file directly inside it is rejected as a single-file plugin.
     *
     * @throws InvalidArgumentException If the file isn't a PHP file in a folder
     *     of its own, or the folder name isn't a plain slug.
     */
    public static function fromFile(string $file, ?string $pluginDir = null): self
    {
        $path = str_replace('\\', '/', $file);
        $directory = dirname($path);
        $slug = basename($directory);
        $name = basename($path);

        if (!str_ends_with(strtolower($name), '.php')) {
            throw new InvalidArgumentException('The main file of a plugin must be a PHP file.');
        }

        if ($pluginDir !== null && rtrim(str_replace('\\', '/', $pluginDir), '/') === rtrim($directory, '/')) {
            throw new InvalidArgumentException('A plugin must be in a folder of its own to be updated. A single file in the plugins folder can\'t be.');
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $slug) !== 1) {
            throw new InvalidArgumentException('The folder of a plugin must be named with letters, digits, dots, hyphens, and underscores.');
        }

        return new self($file, $slug, $slug . '/' . $name);
    }
}
