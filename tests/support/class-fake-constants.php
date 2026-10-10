<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use ScottOffen\WpGithubUpdater\Internal\Platform\Constants;

/**
 * A fake `Constants` for defining the constants that a test reads.
 */
final class FakeConstants implements Constants
{
    /** @var array<string, mixed> */
    private array $defined = [];

    /**
     * Defines a constant for the `has()` and `get()` methods.
     */
    public function define(string $name, mixed $value): void
    {
        $this->defined[$name] = $value;
    }

    public function get(string $name): mixed
    {
        return $this->defined[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->defined);
    }
}
