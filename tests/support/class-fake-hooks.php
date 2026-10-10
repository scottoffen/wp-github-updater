<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use Closure;
use ScottOffen\WpGithubUpdater\Internal\Platform\Hooks;

/**
 * A fake `Hooks` for recording the registered callbacks and for running them.
 */
final class FakeHooks implements Hooks
{
    /**
     * @var array<string, list<array{type: string, callback: Closure, priority:
     *     int, accepted: int}>>
     */
    private array $registered = [];

    public function addAction(string $hook, Closure $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        $this->register('action', $hook, $callback, $priority, $acceptedArgs);
    }

    public function addFilter(string $hook, Closure $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        $this->register('filter', $hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * Runs the callbacks that are registered for a filter, in order of
     * priority. Each callback receives the value and the arguments that it
     * accepts.
     *
     * @return mixed The value after every callback has run.
     */
    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        foreach ($this->registered($hook) as $entry) {
            $params = array_slice([$value, ...$args], 0, $entry['accepted']);
            $value = ($entry['callback'])(...$params);
        }

        return $value;
    }

    /**
     * Runs the callbacks that are registered for an action, in order of
     * priority. Each callback receives the arguments that it accepts.
     */
    public function doAction(string $hook, mixed ...$args): void
    {
        foreach ($this->registered($hook) as $entry) {
            ($entry['callback'])(...array_slice($args, 0, $entry['accepted']));
        }
    }

    /**
     * Returns the callbacks that are registered for a hook, sorted by priority.
     *
     * @return list<array{type: string, callback: Closure, priority: int,
     *     accepted: int}> The entries, each with the type (`filter` or
     *     `action`), the callback, the priority, and the number of accepted
     *     arguments.
     */
    public function registered(string $hook): array
    {
        $entries = $this->registered[$hook] ?? [];

        usort($entries, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return $entries;
    }

    private function register(string $type, string $hook, Closure $callback, int $priority, int $accepted): void
    {
        $this->registered[$hook][] = [
            'type' => $type,
            'callback' => $callback,
            'priority' => $priority,
            'accepted' => $accepted,
        ];
    }
}
