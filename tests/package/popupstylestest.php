<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Package;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Internal\Package\PopupStyles;
use ScottOffen\WpGithubUpdater\Internal\Package\ReleaseNotes;
use ScottOffen\WpGithubUpdater\Internal\Release\Release;
use ScottOffen\WpGithubUpdater\Tests\Support\FakeHtmlSanitizer;
use ScottOffen\WpGithubUpdater\Tests\Support\FakeMessages;

final class PopupStylesTest extends TestCase
{
    private const KINDS = ['note', 'tip', 'important', 'warning', 'caution'];

    private const OPEN = '<style id="wp-github-updater-popup">';

    private const CLOSE = '</style>';


    private static function css(): string
    {
        return substr(PopupStyles::html(), strlen(self::OPEN), -strlen(self::CLOSE));
    }

    public function testItIsOneStyleElementWithAnIdAndNothingElse(): void
    {
        $html = PopupStyles::html();

        $this->assertStringStartsWith(self::OPEN, $html);
        $this->assertStringEndsWith(self::CLOSE, $html);
        $this->assertSame(2, substr_count($html, '<'));
        $this->assertSame(1, substr_count($html, self::CLOSE));
        $this->assertNotSame('', self::css());
        $this->assertSame(0, substr_count(self::css(), '<'));
        $this->assertSame(0, substr_count(self::css(), '>') - substr_count(self::css(), '>p:first-child'));
    }

    public function testEveryKindHasABorderColorAndATitleColorThatAreTheSame(): void
    {
        $html = PopupStyles::html();

        foreach (self::KINDS as $kind) {
            $this->assertSame(1, preg_match('/#section-changelog \.markdown-alert-' . $kind . '\{border-left-color:(#[0-9a-f]{6})\}/', $html, $border), $kind);
            $this->assertSame(1, preg_match('/#section-changelog \.markdown-alert-' . $kind . '>p:first-child\{color:(#[0-9a-f]{6})\}/', $html, $title), $kind);
            $this->assertSame($border[1], $title[1], $kind);
        }
    }

    public function testTheFiveKindsHaveFiveDifferentColors(): void
    {
        preg_match_all('/border-left-color:(#[0-9a-f]{6})/', PopupStyles::html(), $colors);

        $this->assertCount(5, $colors[1]);
        $this->assertCount(5, array_unique($colors[1]));
    }

    public function testEveryRuleIsScopedToTheChangelogAndToAlertBoxes(): void
    {
        $rules = array_filter(explode('}', self::css()));

        $this->assertCount(2 + 2 * count(self::KINDS), $rules);

        foreach ($rules as $rule) {
            $this->assertStringStartsWith('#section-changelog .markdown-alert', $rule);
        }
    }

    public function testTheBoxHasABorderAndTheTitleIsBold(): void
    {
        $html = PopupStyles::html();

        $this->assertStringContainsString('#section-changelog .markdown-alert{margin:0 0 16px;padding:0 1em;border-left:4px solid}', $html);
        $this->assertStringContainsString('#section-changelog .markdown-alert>p:first-child{font-weight:600}', $html);
    }


    public function testTheColorsAreTheOnesTheConverterWritesForEachKind(): void
    {
        $markdown = implode("\n\n", array_map(static fn (string $k): string => "> [!" . strtoupper($k) . "]\n> text", self::KINDS));
        $release = new Release('v1.0.0', '1.0.0', $markdown, '', '', 1, 'my-plugin-1.0.0.zip', 'https://api.github.com/repos/o/r/releases/assets/1', 10, '');
        $html = (new ReleaseNotes(new FakeHtmlSanitizer(), new FakeMessages()))->html($release);
        $css = PopupStyles::html();

        $found = preg_match_all('/<div class="markdown-alert markdown-alert-([a-z]+)" style="[^"]*border-left:4px solid (#[0-9a-f]{6})[^"]*">\s*<p class="markdown-alert-title" style="color:(#[0-9a-f]{6});/', $html, $alerts, PREG_SET_ORDER);

        $this->assertSame(5, $found, $html);

        foreach ($alerts as [, $kind, $border, $title]) {
            $this->assertSame($border, $title, $kind);
            $this->assertStringContainsString('.markdown-alert-' . $kind . '{border-left-color:' . $border . '}', $css, $kind);
            $this->assertStringContainsString('.markdown-alert-' . $kind . '>p:first-child{color:' . $border . '}', $css, $kind);
        }
    }
}
