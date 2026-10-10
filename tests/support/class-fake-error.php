<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;


final class FakeError
{
    public function __construct(public readonly string $outcome)
    {
    }
}
