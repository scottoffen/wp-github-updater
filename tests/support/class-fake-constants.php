<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use ScottOffen\WpGithubUpdater\Internal\Platform\Constants;


final class FakeConstants implements Constants
{
    /** @var array<string, mixed> */
    private array $defined = [];


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
