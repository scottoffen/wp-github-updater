<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\WordPress;

use ScottOffen\WpGithubUpdater\Internal\Platform\OptionStore;

/**
 * @internal
 */
final class WpOptionStore implements OptionStore
{
    public function get(string $name, mixed $default = null): mixed
    {
        return \get_option($name, $default);
    }

    public function set(string $name, mixed $value, bool $autoload = false): void
    {
        \update_option($name, $value, $autoload);
    }

    public function delete(string $name): void
    {
        \delete_option($name);
    }
}
