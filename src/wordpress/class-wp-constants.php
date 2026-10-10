<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\WordPress;

use ScottOffen\WpGithubUpdater\Internal\Platform\Constants;

/**
 * The implementation of the `Constants` interface for the `defined()` and
 * `constant()` functions.
 *
 * @internal
 */
final class WpConstants implements Constants
{
    public function get(string $name): mixed
    {
        return defined($name) ? constant($name) : null;
    }

    public function has(string $name): bool
    {
        return defined($name);
    }
}
