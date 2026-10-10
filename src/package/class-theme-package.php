<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Package;

use InvalidArgumentException;

/**
 * Who a theme is: its `functions.php`, its `style.css`, and its slug.
 *
 * The slug is the name of the folder the theme is installed in, which
 * WordPress calls the stylesheet and uses as the key for the theme. A theme
 * hands over its `functions.php`, so the folder is known to be the theme's
 * own and not some folder inside it.
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
     * @param string $file The path of the theme's `functions.php`, which is `__FILE__` there.
     *
     * @throws InvalidArgumentException If the file isn't `functions.php`, or
     *     the folder name isn't a plain slug.
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
