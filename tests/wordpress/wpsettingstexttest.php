<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\WordPress;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;
use ScottOffen\WpGithubUpdater\Internal\Settings\Notice;
use ScottOffen\WpGithubUpdater\Internal\WordPress\WpSettingsText;
use ScottOffen\WpGithubUpdater\Tests\Support\WpStub;

final class WpSettingsTextTest extends TestCase
{
    private const PROBLEMS = [
        Outcome::AUTH,
        Outcome::NOT_FOUND,
        Outcome::RATE_LIMITED,
        Outcome::NETWORK,
        Outcome::BAD_RESPONSE,
        Outcome::ASSET_MISMATCH,
    ];

    protected function setUp(): void
    {
        WpStub::reset();
    }

    /** @return list<string> Every string the class can produce. */
    private function everyString(): array
    {
        $text = new WpSettingsText();
        $all = [
            $text->tokenLabel(),
            $text->tokenHelp(),
            $text->constantHelp('FDCRM_GITHUB_TOKEN'),
            $text->saveButton(),
            $text->removeButton(),
            $text->checkButton(),
            $text->statusLabel(),
            $text->neverChecked(),
            $text->lastChecked('yesterday'),
            $text->installed('1.0.0'),
            $text->latest('1.1.0'),
            $text->noneFound(),
            $text->problem('unknown'),
        ];

        foreach (self::PROBLEMS as $outcome) {
            $all[] = $text->problem($outcome);
        }

        foreach (Notice::all() as $code) {
            $all[] = $text->notice($code);
        }

        return $all;
    }

    public function testEveryStringIsNotEmptyAndDifferentFromTheOthers(): void
    {
        $strings = $this->everyString();

        foreach ($strings as $string) {
            $this->assertNotSame('', $string);
        }

        $this->assertSame(count($strings), count(array_unique($strings)));
    }

    public function testEveryStringUsesTheLibrariesOwnTextDomain(): void
    {
        $this->everyString();

        $this->assertNotSame([], WpStub::$translated);

        foreach (WpStub::$translated as [$text, $domain]) {
            $this->assertSame('wp-github-updater', $domain, $text);
        }
    }

    public function testNoStringNamesTheUpdateSourceOrHasAnEmDash(): void
    {
        foreach ($this->everyString() as $string) {
            $this->assertStringNotContainsStringIgnoringCase('github', str_replace('FDCRM_GITHUB_TOKEN', '', $string));
            $this->assertStringNotContainsString("\u{2014}", $string);
        }
    }

    public function testTheValuesAreInsertedIntoTheirSentences(): void
    {
        $text = new WpSettingsText();

        $this->assertStringContainsString('FDCRM_GITHUB_TOKEN', $text->constantHelp('FDCRM_GITHUB_TOKEN'));
        $this->assertStringContainsString('Oct 11, 2026', $text->lastChecked('Oct 11, 2026'));
        $this->assertStringContainsString('1.2.3', $text->installed('1.2.3'));
        $this->assertStringContainsString('1.2.4', $text->latest('1.2.4'));
    }

    public function testAValueWithPercentSignsIsInsertedAsItIs(): void
    {
        $text = new WpSettingsText();

        $this->assertStringContainsString('100% of %s and %d', $text->lastChecked('100% of %s and %d'));
        $this->assertStringContainsString('%s', $text->installed('%s'));
    }

    public function testEveryProblemHasItsOwnSentenceAndAnUnknownOneGetsAGeneralSentence(): void
    {
        $text = new WpSettingsText();
        $general = $text->problem('a_code_nobody_wrote_a_sentence_for');
        $seen = [$general];

        $this->assertSame($general, $text->problem(Outcome::OK));
        $this->assertSame($general, $text->problem(''));

        foreach (self::PROBLEMS as $outcome) {
            $this->assertNotContains($text->problem($outcome), $seen, $outcome);
            $seen[] = $text->problem($outcome);
        }
    }

    public function testTheAuthenticationProblemMentionsTheToken(): void
    {
        $this->assertStringContainsStringIgnoringCase('access token', (new WpSettingsText())->problem(Outcome::AUTH));
    }

    public function testEveryNoticeHasItsOwnTextAndAnUnknownCodeHasNone(): void
    {
        $text = new WpSettingsText();
        $seen = [];

        foreach (Notice::all() as $code) {
            $this->assertNotSame('', $text->notice($code), $code);
            $this->assertNotContains($text->notice($code), $seen, $code);
            $seen[] = $text->notice($code);
        }

        foreach (['', 'unknown', 'SAVED', '<script>alert(1)</script>'] as $code) {
            $this->assertSame('', $text->notice($code), $code);
        }
    }
}
