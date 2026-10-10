<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\WordPress;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Internal\WordPress\WpHtmlSanitizer;
use ScottOffen\WpGithubUpdater\Tests\Support\WpStub;

final class WpHtmlSanitizerTest extends TestCase
{
    protected function setUp(): void
    {
        WpStub::reset();
    }

    public function testItReturnsWhatWpKsesPostReturns(): void
    {
        WpStub::$kses = static fn (string $html): string => str_replace('<script>', '', $html);

        $this->assertSame('<p>hi</p>', (new WpHtmlSanitizer())->post('<script><p>hi</p>'));
        $this->assertSame(['<script><p>hi</p>'], WpStub::$ksesInputs);
    }
}
