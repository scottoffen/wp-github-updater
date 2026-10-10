<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use Closure;
use ScottOffen\WpGithubUpdater\Platform\Hooks;

/**
 * Records every callback the library registers, and lets a test run them the
 * way WordPress would: lowest priority number first, each one given only as
 * many arguments as it accepts.
 */
final class FakeHooks implements Hooks
{
    /** @var array<string, list<array{type: string, callback: Closure, priority: int, accepted: int}>> */
    private array $registered = [];

    public function addFilter(string $hook, Closure $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        $this->register('filter', $hook, $callback, $priority, $acceptedArgs);
    }

    public function addAction(string $hook, Closure $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        $this->register('action', $hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * What is registered on a hook, in the order it would run.
     *
     * @return list<array{type: string, callback: Closure, priority: int, accepted: int}>
     */
    public function registered(string $hook): array
    {
        $entries = $this->registered[$hook] ?? [];

        usort($entries, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return $entries;
    }

    /**
     * Runs the filters on a hook, passing the value from one to the next.
     *
     * @return mixed The value after every filter has run.
     */
    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        foreach ($this->registered($hook) as $entry) {
            $params = array_slice([$value, ...$args], 0, $entry['accepted']);
            $value = ($entry['callback'])(...$params);
        }

        return $value;
    }

    /** Runs the actions on a hook. */
    public function doAction(string $hook, mixed ...$args): void
    {
        foreach ($this->registered($hook) as $entry) {
            ($entry['callback'])(...array_slice($args, 0, $entry['accepted']));
        }
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
