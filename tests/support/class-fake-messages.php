<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use ScottOffen\WpGithubUpdater\Platform\Messages;

/**
 * Messages with no translation. An error is a `FakeError` that holds the
 * outcome code, so a test can check which outcome the code under test chose.
 */
final class FakeMessages implements Messages
{
    private ?string $text = null;

    /** Makes `message` return the given text for every outcome. */
    public function returning(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function error(string $outcome): object
    {
        return new FakeError($outcome);
    }

    public function message(string $outcome): string
    {
        return $this->text ?? 'Message for ' . $outcome . '.';
    }

    public function noNotes(): string
    {
        return 'No notes were published.';
    }
}
