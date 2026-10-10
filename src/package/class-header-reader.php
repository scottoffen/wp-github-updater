<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Package;

/**
 * A reader for the headers in text that is formatted like the comment at the
 * top of a plugin file or the `style.css` file of a theme.
 *
 * A header is a line that starts with its name and a colon, after an optional
 * `<?php` and any number of spaces, tabs, and `/`, `*`, `#`, and `@`
 * characters. The name is not case sensitive. The value is the rest of the
 * line, trimmed and without the text from the first `*` followed by `/` or from
 * the first `?>`. Line endings can be `\n`, `\r\n`, or `\r`.
 *
 * @internal
 */
final class HeaderReader
{
    /**
     * Returns the value of a header.
     *
     * @param string $head The text to search for the header.
     * @param string $name The name of the header, such as `Plugin Name`.
     *
     * @return string The value from the first line that has the header, or an
     *     empty string when no line has it.
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
