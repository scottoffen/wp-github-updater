<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Platform;

/**
 * Reads constants that the site owner defines, such as in `wp-config.php`.
 *
 * The interface has no way to define a constant. It only reads the constant
 * that the owner defines for the token.
 *
 * @internal
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
