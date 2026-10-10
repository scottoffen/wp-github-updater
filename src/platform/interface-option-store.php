<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Platform;

/**
 * A store for named values that persist between requests.
 *
 * @internal
 */
interface OptionStore
{
    /**
     * Removes a value. Does nothing when the name isn't stored.
     *
     * @param string $name The name of the value.
     */
    public function delete(string $name): void;

    /**
     * Returns the value stored under a name.
     *
     * @param string $name The name of the value.
     * @param mixed $default The value to return when nothing is stored under
     *     the name.
     *
     * @return mixed The stored value, or `$default` when nothing is stored
     *     under the name.
     */
    public function get(string $name, mixed $default = null): mixed;

    /**
     * Stores a value, replacing any value under the same name.
     *
     * @param string $name The name of the value.
     * @param mixed $value The value to store.
     * @param bool $autoload True to load the value on every request. The
     *     default is false.
     */
    public function set(string $name, mixed $value, bool $autoload = false): void;
}
