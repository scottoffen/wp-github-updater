<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Http;

/**
 * Checks an address before the library sends a request to it.
 *
 * An address is safe to consider when it is absolute, uses `https`, names a
 * host, and has no user, password, or port. It must be printable ASCII with no
 * spaces or backslashes, and its path must have no `.` or `..` segment, even
 * when the segment is percent-encoded. Whether the host is one the library
 * trusts with a token is a separate decision for the caller.
 */
final class SafeUrl
{
    /**
     * Reads an address.
     *
     * @param string $url The address to check.
     *
     * @return array{host: string, path: string}|null The host in lowercase and
     *     the path, which is an empty string when the address has none, or null
     *     when the address isn't safe.
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
