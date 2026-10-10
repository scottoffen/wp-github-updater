<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Http;

/**
 * @internal
 */
final class SafeUrl
{
    /**
     * @param string $url The URL to validate.
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
