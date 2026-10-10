<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\WordPress;

use Closure;
use ScottOffen\WpGithubUpdater\Internal\Platform\Hooks;

/**
 * @internal
 */
final class WpHooks implements Hooks
{
    public function addFilter(string $hook, Closure $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        \add_filter($hook, $callback, $priority, $acceptedArgs);
    }

    public function addAction(string $hook, Closure $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        \add_action($hook, $callback, $priority, $acceptedArgs);
    }
}
