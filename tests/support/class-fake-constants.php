<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use ScottOffen\WpGithubUpdater\Platform\Constants;

/**
 * Constants that exist only inside the test, so nothing is ever defined for real.
 */
final class FakeConstants implements Constants
{
    /** @var array<string, mixed> */
    private array $defined = [];

    /** Defines a constant for the test. */
    public function define(string $name, mixed $value): void
    {
        $this->defined[$name] = $value;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->defined);
    }

    public function get(string $name): mixed
    {
        return $this->defined[$name] ?? null;
    }
}
