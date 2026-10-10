<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\WordPress;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Release\Outcome;
use ScottOffen\WpGithubUpdater\Tests\Support\WpStub;
use ScottOffen\WpGithubUpdater\WordPress\WpMessages;
use WP_Error;

final class WpMessagesTest extends TestCase
{
    private const CODES = [
        Outcome::AUTH,
        Outcome::NOT_FOUND,
        Outcome::RATE_LIMITED,
        Outcome::NETWORK,
        Outcome::BAD_RESPONSE,
        Outcome::DIGEST_MISMATCH,
        Outcome::FILE_ERROR,
        Outcome::REFUSED,
        Outcome::FOLDER_MISMATCH,
        Outcome::NO_RELEASE,
    ];

    protected function setUp(): void
    {
        WpStub::reset();
    }

    private function message(string $outcome): string
    {
        $error = (new WpMessages())->error($outcome);

        $this->assertInstanceOf(WP_Error::class, $error);

        return $error->get_error_message();
    }

    public function testEveryOutcomeBecomesAWpErrorWithItsOwnCodeAndMessage(): void
    {
        $seen = [];

        foreach (self::CODES as $outcome) {
            $error = (new WpMessages())->error($outcome);

            $this->assertInstanceOf(WP_Error::class, $error, $outcome);
            $this->assertSame('wp_github_updater_' . $outcome, $error->get_error_code(), $outcome);
            $this->assertNotSame('', $error->get_error_message(), $outcome);
            $this->assertNotContains($error->get_error_message(), $seen, $outcome);

            $seen[] = $error->get_error_message();
        }
    }

    public function testTheMessageForAnOutcomeIsTheSameTextTheErrorCarries(): void
    {
        foreach ([...self::CODES, 'unknown'] as $outcome) {
            $this->assertSame($this->message($outcome), (new WpMessages())->message($outcome), $outcome);
        }
    }

    public function testAnOutcomeWithNoMessageOfItsOwnGetsAGeneralOne(): void
    {
        $general = $this->message('a_code_nobody_wrote_a_message_for');

        $this->assertSame('The update could not be completed.', $general);
        $this->assertSame($general, $this->message(Outcome::OK));
        $this->assertSame('wp_github_updater_a_code_nobody_wrote_a_message_for', (new WpMessages())->error('a_code_nobody_wrote_a_message_for')->get_error_code());
    }

    public function testEveryStringUsesTheLibrariesOwnTextDomain(): void
    {
        $messages = new WpMessages();

        foreach (self::CODES as $outcome) {
            $messages->error($outcome);
        }

        $messages->error('unknown');
        $messages->noNotes();

        $this->assertNotSame([], WpStub::$translated);

        foreach (WpStub::$translated as [$text, $domain]) {
            $this->assertSame('wp-github-updater', $domain, $text);
        }
    }

    public function testTheNoNotesSentenceIsAskedForByItself(): void
    {
        $this->assertSame('No release notes were published for this version.', (new WpMessages())->noNotes());
        $this->assertSame([['No release notes were published for this version.', 'wp-github-updater']], WpStub::$translated);
    }

    public function testNoMessageNamesTheUpdateSource(): void
    {
        $all = array_map(fn (string $outcome): string => $this->message($outcome), [...self::CODES, 'unknown']);
        $all[] = (new WpMessages())->noNotes();

        foreach ($all as $text) {
            $this->assertStringNotContainsStringIgnoringCase('github', $text);
            $this->assertStringNotContainsString("\u{2014}", $text);
        }
    }
}
