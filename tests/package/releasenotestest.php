<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Package;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use ScottOffen\WpGithubUpdater\Internal\Package\ReleaseNotes;
use ScottOffen\WpGithubUpdater\Internal\Release\Release;
use ScottOffen\WpGithubUpdater\Tests\Support\FakeHtmlSanitizer;
use ScottOffen\WpGithubUpdater\Tests\Support\FakeMessages;

final class ReleaseNotesTest extends TestCase
{
    private const GENERATED = <<<'MD'
## What's Changed
* Fix the thing by @octocat in https://github.com/owner/repo/pull/12
* Add the other thing by @hubot in https://github.com/owner/repo/pull/13

## New Contributors
* @hubot made their first contribution in https://github.com/owner/repo/pull/13

**Full Changelog**: https://github.com/owner/repo/compare/v1.0.0...v1.1.0
MD;

    private FakeHtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new FakeHtmlSanitizer();
    }

    private function notes(): ReleaseNotes
    {
        return new ReleaseNotes($this->sanitizer, new FakeMessages());
    }

    private function release(string $notes): Release
    {
        return new Release('v1.1.0', '1.1.0', $notes, '', '', 1, 'my-plugin-1.1.0.zip', 'https://api.github.com/repos/o/r/releases/assets/1', 10, '');
    }

    private function html(string $markdown): string
    {
        return $this->notes()->html($this->release($markdown));
    }

    public function testGeneratedNotesBecomeHtml(): void
    {
        $html = $this->html(self::GENERATED);

        $this->assertStringContainsString("<h6>What's Changed</h6>", $html);
        $this->assertStringContainsString("<h6>New Contributors</h6>", $html);
        $this->assertStringContainsString('<li>Fix the thing by @octocat in <a href="https://github.com/owner/repo/pull/12"', $html);
        $this->assertStringContainsString('<strong>Full Changelog</strong>', $html);
        $this->assertStringContainsString('https://github.com/owner/repo/compare/v1.0.0...v1.1.0', $html);
    }

    public function testWindowsLineEndingsConvertTheSameWay(): void
    {
        $this->assertSame($this->html(self::GENERATED), $this->html(str_replace("\n", "\r\n", self::GENERATED)));
    }

    public function testHeadingsAreOffsetByFour(): void
    {
        $html = $this->html("# One\n\n## Two\n\n### Three\n\n#### Four\n");

        $this->assertStringContainsString('<h5>One</h5>', $html);
        $this->assertStringContainsString('<h6>Two</h6>', $html);
        $this->assertStringContainsString('<h6>Three</h6>', $html);
        $this->assertStringNotContainsString('<h1', $html);
        $this->assertStringNotContainsString('<h2', $html);
    }

    public function testRawHtmlIsSanitizedByTheConverter(): void
    {
        $html = $this->html("Hello <script>alert(1)</script> <img src=x onerror=alert(1)> world\n\n<iframe src=\"https://evil.example\"></iframe>\n");

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringContainsString('Hello', $html);
    }

    public function testDetailsInBlockFormSurvive(): void
    {
        $html = $this->html("<details>\n<summary>More</summary>\n\nHidden **text**\n\n</details>\n");

        $this->assertStringContainsString('<details>', $html);
        $this->assertStringContainsString('<summary>More</summary>', $html);
        $this->assertStringContainsString('<strong>text</strong>', $html);
    }

    public function testImagesFromGitHubHostsAreRenderedAndOthersAreNot(): void
    {
        $rendered = $this->html('![a](https://github.com/owner/repo/assets/1/a.png)');
        $alsoKept = $this->html('![b](https://avatars.githubusercontent.com/u/1)');
        $dropped = $this->html('![c](https://evil.example/tracker.png)');

        $this->assertStringContainsString('<img src="https://github.com/owner/repo/assets/1/a.png"', $rendered);
        $this->assertStringContainsString('<img src="https://avatars.githubusercontent.com/u/1"', $alsoKept);
        $this->assertStringNotContainsString('<img', $dropped);
        $this->assertStringContainsString('<a href="https://evil.example/tracker.png"', $dropped);
        $this->assertStringContainsString('>c</a>', $dropped);
    }

    public function testInlineStylesAreOn(): void
    {
        $html = $this->html("| a | b |\n|---|---|\n| 1 | 2 |\n\n> [!NOTE]\n> Heads up\n");

        $this->assertStringContainsString('<table style="', $html);
        $this->assertStringContainsString('markdown-alert-note" style="', $html);
    }

    public function testReferenceLinksAreResolved(): void
    {
        $html = $this->html("See [the docs][docs].\n\n[docs]: https://github.com/owner/repo/wiki\n");

        $this->assertStringContainsString('<a href="https://github.com/owner/repo/wiki"', $html);
        $this->assertStringContainsString('>the docs</a>', $html);
    }

    public function testEmojiShortcodesAreConverted(): void
    {
        $html = $this->html('Shipped :tada:');

        $this->assertStringContainsString("\u{1F389}", $html);
        $this->assertStringNotContainsString(':tada:', $html);
    }

    public function testTheConvertedHtmlGoesThroughTheSanitizer(): void
    {
        $this->sanitizer->using(static fn (string $html): string => str_replace('Hello', 'SANITIZED', $html));

        $html = $this->html('Hello world');

        $this->assertCount(1, $this->sanitizer->inputs());
        $this->assertStringContainsString('<p>Hello world</p>', $this->sanitizer->inputs()[0]);
        $this->assertStringContainsString('SANITIZED world', $html);
        $this->assertStringNotContainsString('Hello', $html);
    }

    public function testWhatTheSanitizerReturnsIsTrimmed(): void
    {
        $this->sanitizer->using(static fn (string $html): string => "\n  <p>kept</p>  \n\n");

        $this->assertSame('<p>kept</p>', $this->html('anything'));
    }

    public function testAReleaseWithNoNotesGetsTheSentence(): void
    {
        foreach (['', '   ', "\n\n", "\r\n \t"] as $notes) {
            $this->assertSame('<p>No notes were published.</p>', $this->html($notes), var_export($notes, true));
        }

        $this->assertSame([], $this->sanitizer->inputs());
    }

    public function testNotesThatLeaveNothingAfterCleaningGetTheSentence(): void
    {
        $this->sanitizer->using(static fn (string $html): string => "  \n");

        $this->assertSame('<p>No notes were published.</p>', $this->html('Some notes'));
    }

    public function testTheSentenceIsEscaped(): void
    {
        $notes = new ReleaseNotes($this->sanitizer, new class () implements \ScottOffen\WpGithubUpdater\Internal\Platform\Messages {
            public function error(string $outcome): object
            {
                return new \stdClass();
            }

            public function message(string $outcome): string
            {
                return '';
            }

            public function noNotes(): string
            {
                return 'Nothing <b>here</b> & "more"';
            }
        });

        $this->assertSame('<p>Nothing &lt;b&gt;here&lt;/b&gt; &amp; &quot;more&quot;</p>', $notes->html($this->release('')));
    }

    public function testTheConverterIsBuiltOnlyWhenNotesAreFirstConverted(): void
    {
        $notes = $this->notes();
        $property = new ReflectionProperty(ReleaseNotes::class, 'converter');

        $this->assertNull($property->getValue($notes));

        $notes->html($this->release(''));
        $this->assertNull($property->getValue($notes));

        $notes->html($this->release('Some notes'));
        $first = $property->getValue($notes);
        $this->assertNotNull($first);

        $notes->html($this->release('More notes'));
        $this->assertSame($first, $property->getValue($notes));
    }
}
