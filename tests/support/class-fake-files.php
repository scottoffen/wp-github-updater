<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use ScottOffen\WpGithubUpdater\Internal\Platform\Files;


final class FakeFiles implements Files
{
    /** @var array<string, string> */
    private array $files = [];

    private int $temps = 0;

    private bool $refuseTemps = false;


    public function refuseTempFiles(): void
    {
        $this->refuseTemps = true;
    }


    public function put(string $path, string $contents): void
    {
        $this->files[$path] = $contents;
    }


    public function exists(string $path): bool
    {
        return array_key_exists($path, $this->files);
    }


    public function contents(string $path): ?string
    {
        return $this->files[$path] ?? null;
    }

    public function readHead(string $path, int $bytes = 8192): ?string
    {
        return isset($this->files[$path]) ? substr($this->files[$path], 0, $bytes) : null;
    }

    public function children(string $path): ?array
    {
        $prefix = rtrim($path, '/') . '/';
        $names = [];

        foreach (array_keys($this->files) as $file) {
            if (str_starts_with($file, $prefix)) {
                $names[explode('/', substr($file, strlen($prefix)))[0]] = true;
            }
        }

        return $names === [] ? null : array_map('strval', array_keys($names));
    }

    public function tempFile(): ?string
    {
        if ($this->refuseTemps) {
            return null;
        }

        $path = '/tmp/fake-temp-' . (++$this->temps);
        $this->files[$path] = '';

        return $path;
    }

    public function hash(string $path, string $algorithm): ?string
    {
        return isset($this->files[$path]) ? hash($algorithm, $this->files[$path]) : null;
    }

    public function delete(string $path): void
    {
        unset($this->files[$path]);
    }
}
