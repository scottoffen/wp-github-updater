<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use ScottOffen\WpGithubUpdater\Internal\Platform\Messages;


final class FakeMessages implements Messages
{
    private ?string $text = null;


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
