<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Platform;

/**
 * The hook system for registering callbacks for filters and actions.
 *
 * A callback is a `Closure`.
 *
 * @internal
 */
interface Hooks
{
    /**
     * Registers a callback for an action.
     *
     * @param string $hook The name of the action.
     * @param \Closure $callback The closure to call when the action runs.
     * @param int $priority The priority of the callback. Lower numbers run
     *     first.
     * @param int $acceptedArgs The number of arguments that the callback
     *     accepts.
     */
    public function addAction(string $hook, \Closure $callback, int $priority = 10, int $acceptedArgs = 1): void;

    /**
     * Registers a callback for a filter. The callback receives a value and
     * returns it, changed or not.
     *
     * @param string $hook The name of the filter.
     * @param \Closure $callback The closure to call when the filter runs.
     * @param int $priority The priority of the callback. Lower numbers run
     *     first.
     * @param int $acceptedArgs The number of arguments that the callback
     *     accepts.
     */
    public function addFilter(string $hook, \Closure $callback, int $priority = 10, int $acceptedArgs = 1): void;
}
