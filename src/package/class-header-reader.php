<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Package;

/**
 * Reads a header, such as `Version`, from text in the format of the comment at
 * the top of a plugin file or the `style.css` file of a theme.
 *
 * It follows the rules WordPress uses for plugin headers. A header is a line
 * that starts with its name and a colon, after any `<?php`, spaces, and comment
 * characters. The name is not case sensitive, and the first match is used. The
 * value runs to the end of the line, and a closing `*` and `/` or `?>` on the
 * same line is not part of it. Line endings can be `\n`, `\r\n`, or `\r`.
 *
 * @internal
 */
final class HeaderReader
{
    /**
     * @param string $head The text to search for the header.
     * @param string $name The name of the header, such as `Plugin Name`.
     *
     * @return string The value, or an empty string when the header isn't there.
     */
    public function read(string $head, string $name): string
    {
        $head = str_replace("\r", "\n", $head);
        $pattern = '/^(?:[ \t]*<\?php)?[ \t\/*#@]*' . preg_quote($name, '/') . ':(.*)$/mi';

        if (preg_match($pattern, $head, $match) !== 1) {
            return '';
        }

        return trim((string) preg_replace('/\s*(?:\*\/|\?>).*/', '', $match[1]));
    }
}
