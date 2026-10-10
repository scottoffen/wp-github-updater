<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use Closure;
use ScottOffen\WpGithubUpdater\Internal\Platform\Hooks;


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
     * @return list<array{type: string, callback: Closure, priority: int,
     *     accepted: int}>
     */
    public function registered(string $hook): array
    {
        $entries = $this->registered[$hook] ?? [];

        usort($entries, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return $entries;
    }

    /**
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
