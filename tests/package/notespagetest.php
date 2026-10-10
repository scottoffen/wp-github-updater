<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Package;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Package\NotesPage;

final class NotesPageTest extends TestCase
{
    private function page(string $title = 'My Theme 1.1.0', string $notes = '<p>Notes</p>'): string
    {
        return (new NotesPage())->html($title, $notes);
    }

    public function testItIsAWholeHtmlDocument(): void
    {
        $html = $this->page();

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<meta charset="utf-8">', $html);
        $this->assertStringContainsString('<meta name="viewport"', $html);
        $this->assertStringContainsString('<style>', $html);
        $this->assertStringEndsWith('</html>', $html);
    }

    public function testTheTitleIsTheTitleOfThePageAndTheFirstHeading(): void
    {
        $html = $this->page('My Theme 1.1.0');

        $this->assertStringContainsString('<title>My Theme 1.1.0</title>', $html);
        $this->assertStringContainsString('<h1>My Theme 1.1.0</h1>', $html);
    }

    public function testTheTitleIsEscaped(): void
    {
        $html = $this->page('<script>alert(1)</script> "Theme" & \'more\'');

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; &quot;Theme&quot; &amp; &#039;more&#039;', $html);
        $this->assertSame(2, substr_count($html, '&lt;script&gt;'));
    }

    public function testTheNotesAreInsertedAsTheyAre(): void
    {
        $notes = '<h6>What&#039;s Changed</h6><ul><li>A <a href="https://example.com/x?a=1&amp;b=2">change</a></li></ul>';

        $this->assertStringContainsString("<h1>My Theme 1.1.0</h1>\n" . $notes . "\n</body>", $this->page(notes: $notes));
    }

    public function testThePageKeepsSearchEnginesAwayAndRunsNoScripts(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function testAStyleSheetOfDollarSignsOrBracesDoesNotBreakTheNotes(): void
    {
        $html = $this->page(notes: '<p>$price {$x} {braces}</p>');

        $this->assertStringContainsString('<p>$price {$x} {braces}</p>', $html);
    }
}
