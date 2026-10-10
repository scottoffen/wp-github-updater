<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Package;

use InvalidArgumentException;

/**
 * A package for the main file of a plugin: the path of the file, the slug, and
 * the key.
 *
 * The slug is the name of the folder that the main file is in. The key is the
 * slug and the name of the main file, joined with a slash.
 *
 * @internal
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
     * Returns the package for the main file of a plugin.
     *
     * A plugin must be in a folder inside the plugins folder, so the method
     * throws an exception when the main file is directly in the plugins folder.
     *
     * @param string $file The path of the main file of the plugin.
     * @param string $pluginDir The path of the plugins folder.
     *
     * @return self The package. The `file` value is the path as it was given.
     *
     * @throws InvalidArgumentException If the file name doesn't end in `.php`,
     *     the file is directly in the plugins folder, or the folder name isn't
     *     letters, digits, dots, hyphens, and underscores that start with a
     *     letter or a digit.
     */
    public static function fromFile(string $file, string $pluginDir): self
    {
        $path = str_replace('\\', '/', $file);
        $directory = dirname($path);
        $slug = basename($directory);
        $name = basename($path);

        if (!str_ends_with(strtolower($name), '.php')) {
            throw new InvalidArgumentException('The main file of a plugin must be a PHP file.');
        }

        if (rtrim(str_replace('\\', '/', $pluginDir), '/') === rtrim($directory, '/')) {
            throw new InvalidArgumentException('A plugin must be installed in a folder inside the plugins folder. A single file in the plugins folder can\'t be updated.');
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $slug) !== 1) {
            throw new InvalidArgumentException('The folder of a plugin must be named with letters, digits, dots, hyphens, and underscores.');
        }

        return new self($file, $slug, $slug . '/' . $name);
    }
}
