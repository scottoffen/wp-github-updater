<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Http;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Http\SafeUrl;

final class SafeUrlTest extends TestCase
{
    public function testAnAbsoluteHttpsAddressIsReadIntoHostAndPath(): void
    {
        $this->assertSame(
            ['host' => 'api.github.com', 'path' => '/repos/owner/repo/releases/assets/42'],
            SafeUrl::parse('https://api.github.com/repos/owner/repo/releases/assets/42'),
        );
    }

    public function testTheHostIsLowercasedAndTheSchemeMayBeInAnyCase(): void
    {
        $this->assertSame(['host' => 'api.github.com', 'path' => '/x'], SafeUrl::parse('HTTPS://API.GitHub.COM/x'));
    }

    public function testTheQueryIsLeftOutOfThePath(): void
    {
        $parts = SafeUrl::parse('https://objects.example.com/a/b.zip?X-Signature=abc%2Fdef&expires=1');

        $this->assertSame(['host' => 'objects.example.com', 'path' => '/a/b.zip'], $parts);
    }

    public function testAnAddressWithNoPathHasAnEmptyPath(): void
    {
        $this->assertSame(['host' => 'example.com', 'path' => ''], SafeUrl::parse('https://example.com'));
        $this->assertSame(['host' => 'example.com', 'path' => ''], SafeUrl::parse('https://example.com?a=1'));
    }

    public function testAddressesThatAreNotSafeAreRejected(): void
    {
        $bad = [
            '',
            'example.com/file.zip',
            '/relative/path',
            '//example.com/file.zip',
            'http://example.com/file.zip',
            'ftp://example.com/file.zip',
            'file:///etc/passwd',
            'javascript:alert(1)',
            'https://',
            'https:///path',
            'https://example.com:8443/file.zip',
            'https://example.com:443/file.zip',
            'https://user@example.com/file.zip',
            'https://user:pass@example.com/file.zip',
            'https://example.com@evil.example/file.zip',
            'https://example.com\\@evil.example/file.zip',
            'https://example.com/a\\b',
            'https://example.com/a b',
            "https://example.com/a\tb",
            "https://example.com/a\r\nX-Evil: 1",
            "https://example.com/\0",
            "https://example.com/caf\u{e9}",
            'https://example.com/../etc',
            'https://example.com/a/./b',
            'https://example.com/a/%2e%2e/b',
            'https://example.com/a/%2E/b',
            'https://example.com/a/..',
        ];

        foreach ($bad as $url) {
            $this->assertNull(SafeUrl::parse($url), var_export($url, true));
        }
    }

    public function testDotsInsideANameAreFine(): void
    {
        $this->assertNotNull(SafeUrl::parse('https://example.com/my.plugin-1.0.0.zip'));
        $this->assertNotNull(SafeUrl::parse('https://example.com/a/.hidden/b'));
        $this->assertNotNull(SafeUrl::parse('https://example.com/a/..b/c'));
    }
}
