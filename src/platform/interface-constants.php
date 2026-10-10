<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Platform;

/**
 * A reader for the constants that are defined in PHP, such as in
 * `wp-config.php`.
 *
 * The interface has no method for defining a constant.
 *
 * @internal
 */
interface Constants
{
    /**
     * Returns the value of a constant.
     *
     * @param string $name The name of the constant.
     *
     * @return mixed The value of the constant, or null when it isn't defined.
     */
    public function get(string $name): mixed;

    /**
     * Indicates whether a constant is defined.
     *
     * @param string $name The name of the constant.
     *
     * @return bool True when the constant is defined.
     */
    public function has(string $name): bool;
}
