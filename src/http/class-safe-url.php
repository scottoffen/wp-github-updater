<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Http;

/**
 * A URL validator for splitting a valid URL into its host and path.
 *
 * @internal
 */
final class SafeUrl
{
    /**
     * Validates a URL and returns its host and path.
     *
     * The following list describes the conditions that a URL must meet.
     *
     * - The URL must contain only the printable ASCII characters from `!` to
     *   `~`, with no backslash. A space is not allowed.
     * - The scheme must be `https`.
     * - The host must not be empty.
     * - The URL must not contain a user name, a password, or a port. A port is
     *   rejected even when it is 443.
     * - No segment of the path may be `.` or `..`. The path is percent-decoded
     *   before it is split into segments, so `%2e%2e` is rejected too.
     *
     * @param string $url The URL to validate.
     *
     * @return array{host: string, path: string}|null The host in lowercase and
     *     the path as written, which is an empty string when the URL has none,
     *     or null when the URL doesn't meet the conditions.
     */
    public static function parse(string $url): ?array
    {
        if (preg_match('/^[\x21-\x7e]+$/D', $url) !== 1 || str_contains($url, '\\')) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https') {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');

        if ($host === '' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }

        $path = $parts['path'] ?? '';

        foreach (explode('/', rawurldecode($path)) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return null;
            }
        }

        return ['host' => $host, 'path' => $path];
    }
}
