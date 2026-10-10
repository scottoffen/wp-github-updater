<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use ScottOffen\WpGithubUpdater\Internal\Platform\OptionStore;

/**
 * A fake `OptionStore` for keeping values in memory.
 */
final class FakeOptionStore implements OptionStore
{
    /** @var array<string, mixed> */
    private array $values = [];

    /** @var array<string, bool> */
    private array $autoload = [];

    /**
     * Returns the autoload flag that the `set()` method received for a name.
     *
     * @return bool|null The flag, or null when nothing is stored under the
     *     name.
     */
    public function autoloads(string $name): ?bool
    {
        return $this->autoload[$name] ?? null;
    }

    public function delete(string $name): void
    {
        unset($this->values[$name], $this->autoload[$name]);
    }

    public function get(string $name, mixed $default = null): mixed
    {
        return array_key_exists($name, $this->values) ? $this->values[$name] : $default;
    }

    /**
     * Indicates whether a value is stored under a name.
     *
     * @return bool True when a value is stored under the name.
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->values);
    }

    /**
     * Returns the names of the stored values.
     *
     * @return list<string> The names.
     */
    public function names(): array
    {
        return array_keys($this->values);
    }

    public function set(string $name, mixed $value, bool $autoload = false): void
    {
        $this->values[$name] = $value;
        $this->autoload[$name] = $autoload;
    }
}
