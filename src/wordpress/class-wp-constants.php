<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\WordPress;

use ScottOffen\WpGithubUpdater\Platform\Constants;

/**
 * Reads constants the site owner defined, such as in `wp-config.php`.
 */
final class WpConstants implements Constants
{
    public function has(string $name): bool
    {
        return defined($name);
    }

    public function get(string $name): mixed
    {
        return defined($name) ? constant($name) : null;
    }
}
