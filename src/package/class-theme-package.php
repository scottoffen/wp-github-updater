<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Package;

use InvalidArgumentException;

/**
 * A package for the `functions.php` file of a theme: the path of the file, the
 * slug, the key, and the path of the `style.css` file.
 *
 * The slug and the key are both the name of the folder that the `functions.php`
 * file is in. The path of the `style.css` file is the path of the
 * `functions.php` file with `functions.php` replaced by `style.css`.
 *
 * @internal
 */
final class ThemePackage
{
    private const FUNCTIONS = 'functions.php';

    private function __construct(
        public readonly string $file,
        public readonly string $slug,
        public readonly string $key,
        public readonly string $styleFile,
    ) {
    }

    /**
     * Returns the package for the `functions.php` file of a theme.
     *
     * @param string $file The path of the `functions.php` file of the theme.
     *
     * @return self The package. The `file` value is the path as it was given.
     *
     * @throws InvalidArgumentException If the file name isn't `functions.php`,
     *     or the folder name isn't letters, digits, dots, hyphens, and
     *     underscores that start with a letter or a digit.
     */
    public static function fromFile(string $file): self
    {
        $path = str_replace('\\', '/', $file);

        if (basename($path) !== self::FUNCTIONS) {
            throw new InvalidArgumentException('Pass the functions.php of the theme, which is __FILE__ there.');
        }

        $slug = basename(dirname($path));

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $slug) !== 1) {
            throw new InvalidArgumentException('The folder of a theme must be named with letters, digits, dots, hyphens, and underscores.');
        }

        return new self($file, $slug, $slug, substr($file, 0, -strlen(self::FUNCTIONS)) . 'style.css');
    }
}
