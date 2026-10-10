<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use ScottOffen\WpGithubUpdater\Internal\Platform\Messages;

/**
 * A fake `Messages` for returning predictable messages and error objects.
 */
final class FakeMessages implements Messages
{
    private ?string $text = null;

    /**
     * Returns a `FakeError` for the outcome.
     *
     * @return object The error.
     */
    public function error(string $outcome): object
    {
        return new FakeError($outcome);
    }

    /**
     * Returns the text from the `returning()` method, or `Message for `
     * followed by the outcome and a period.
     *
     * @return string The message.
     */
    public function message(string $outcome): string
    {
        return $this->text ?? 'Message for ' . $outcome . '.';
    }

    /**
     * Returns the text `No notes were published.`
     *
     * @return string The message.
     */
    public function noNotes(): string
    {
        return 'No notes were published.';
    }

    /**
     * Sets the text that the `message()` method returns for every outcome.
     *
     * @return self The fake.
     */
    public function returning(string $text): self
    {
        $this->text = $text;

        return $this;
    }
}
