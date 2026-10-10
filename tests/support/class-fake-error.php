<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

/**
 * Stands in for a `WP_Error`, and remembers the outcome code it was made for.
 */
final class FakeError
{
    public function __construct(public readonly string $outcome)
    {
    }
}
