<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use ScottOffen\WpGithubUpdater\Internal\Platform\OptionStore;


final class FakeOptionStore implements OptionStore
{
    /** @var array<string, mixed> */
    private array $values = [];

    /** @var array<string, bool> */
    private array $autoload = [];

    public function get(string $name, mixed $default = null): mixed
    {
        return array_key_exists($name, $this->values) ? $this->values[$name] : $default;
    }

    public function set(string $name, mixed $value, bool $autoload = false): void
    {
        $this->values[$name] = $value;
        $this->autoload[$name] = $autoload;
    }

    public function delete(string $name): void
    {
        unset($this->values[$name], $this->autoload[$name]);
    }


    public function has(string $name): bool
    {
        return array_key_exists($name, $this->values);
    }


    public function autoloads(string $name): ?bool
    {
        return $this->autoload[$name] ?? null;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->values);
    }
}
