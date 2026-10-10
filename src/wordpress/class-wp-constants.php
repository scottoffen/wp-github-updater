<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\WordPress;

use ScottOffen\WpGithubUpdater\Internal\Platform\Constants;

/**
 * @internal
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
