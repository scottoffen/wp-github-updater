<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Platform;

/**
 * Reads constants that the site owner defines, such as in `wp-config.php`.
 *
 * The library never defines a constant. It only reads the one the owner picks
 * for the token.
 */
interface Constants
{
    /**
     * @param string $name The name of the constant.
     *
     * @return bool Whether the constant is defined.
     */
    public function has(string $name): bool;

    /**
     * @param string $name The name of the constant.
     *
     * @return mixed The value of the constant, or null when it isn't defined.
     */
    public function get(string $name): mixed;
}
