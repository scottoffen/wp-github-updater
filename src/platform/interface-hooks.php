<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Platform;

/**
 * Registers callbacks with the platform's hook system.
 *
 * Callbacks are closures, so the library never names a class or a function
 * with a string.
 */
interface Hooks
{
    /**
     * Registers a callback that receives a value and returns it, changed or not.
     *
     * @param string $hook The name of the filter.
     * @param \Closure $callback The callback.
     * @param int $priority Lower numbers run first.
     * @param int $acceptedArgs How many arguments the callback accepts.
     */
    public function addFilter(string $hook, \Closure $callback, int $priority = 10, int $acceptedArgs = 1): void;

    /**
     * Registers a callback that runs when the platform fires an action.
     *
     * @param string $hook The name of the action.
     * @param \Closure $callback The callback.
     * @param int $priority Lower numbers run first.
     * @param int $acceptedArgs How many arguments the callback accepts.
     */
    public function addAction(string $hook, \Closure $callback, int $priority = 10, int $acceptedArgs = 1): void;
}
