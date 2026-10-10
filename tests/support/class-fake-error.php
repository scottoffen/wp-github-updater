<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

/**
 * A fake error object that has the outcome of an update.
 */
final class FakeError
{
    public function __construct(public readonly string $outcome)
    {
    }
}
