<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Platform;

/**
 * Reads and writes named values that persist between requests.
 *
 * The library stores plain arrays and strings here, never objects.
 *
 * @internal
 */
interface OptionStore
{
    /**
     * @param string $name The name of the value.
     * @param mixed $default What to return when nothing is stored under the name.
     */
    public function get(string $name, mixed $default = null): mixed;

    /**
     * Stores a value, replacing any value under the same name.
     *
     * @param string $name The name of the value.
     * @param mixed $value The value to store.
     * @param bool $autoload Whether the platform should load the value on every
     *     request. It is false by default.
     */
    public function set(string $name, mixed $value, bool $autoload = false): void;

    /**
     * Removes a value. Does nothing when the name isn't stored.
     */
    public function delete(string $name): void;
}
