<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use ScottOffen\WpGithubUpdater\Internal\Platform\Files;

/**
 * A fake `Files` for keeping files in memory.
 */
final class FakeFiles implements Files
{
    /** @var array<string, string> */
    private array $files = [];

    private int $temps = 0;

    private bool $refuseTemps = false;

    /**
     * Returns the contents of a file.
     *
     * @return string|null The contents, or null when the file doesn't exist.
     */
    public function contents(string $path): ?string
    {
        return $this->files[$path] ?? null;
    }

    public function delete(string $path): void
    {
        unset($this->files[$path]);
    }

    /**
     * Indicates whether a file exists.
     *
     * @return bool True when the file exists.
     */
    public function exists(string $path): bool
    {
        return array_key_exists($path, $this->files);
    }

    public function hash(string $path, string $algorithm): ?string
    {
        return isset($this->files[$path]) ? hash($algorithm, $this->files[$path]) : null;
    }

    /**
     * Creates a file with the contents, or replaces the contents of an existing
     * file.
     */
    public function put(string $path, string $contents): void
    {
        $this->files[$path] = $contents;
    }

    public function readHead(string $path, int $bytes = 8192): ?string
    {
        return isset($this->files[$path]) ? substr($this->files[$path], 0, $bytes) : null;
    }

    /**
     * Makes the `tempFile()` method return null from then on.
     */
    public function refuseTempFiles(): void
    {
        $this->refuseTemps = true;
    }

    /**
     * Creates an empty file named `/tmp/fake-temp-` followed by a number that
     * increases with each call, unless the `refuseTempFiles()` method was
     * called.
     *
     * @return string|null The path of the new file, or null after the
     *     `refuseTempFiles()` method was called.
     */
    public function tempFile(): ?string
    {
        if ($this->refuseTemps) {
            return null;
        }

        $path = '/tmp/fake-temp-' . (++$this->temps);
        $this->files[$path] = '';

        return $path;
    }
}
