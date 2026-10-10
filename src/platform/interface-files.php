<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Platform;

/**
 * The few file operations that the library uses.
 *
 * @internal
 */
interface Files
{
    /**
     * Reads the first bytes of a file, which is where the headers of a plugin
     * or theme are.
     *
     * @param string $path The path of the file.
     * @param int $bytes The most bytes to read.
     *
     * @return string|null The bytes read, or null when the file can't be read.
     */
    public function readHead(string $path, int $bytes = 8192): ?string;

    /**
     * Lists what is directly inside a directory.
     *
     * @param string $path The path of the directory.
     *
     * @return list<string>|null The names of the entries, without the path and
     *     in no particular order, or null when the directory can't be read.
     */
    public function children(string $path): ?array;

    /**
     * Creates an empty temporary file.
     *
     * @return string|null The path of the new file, or null when it can't be created.
     */
    public function tempFile(): ?string;

    /**
     * Hashes the contents of a file.
     *
     * @param string $path The path of the file.
     * @param string $algorithm The name of the algorithm, such as `sha256`.
     *
     * @return string|null The hash as lowercase hexadecimal, or null when the
     *     file can't be read.
     */
    public function hash(string $path, string $algorithm): ?string;

    /**
     * Deletes a file. Does nothing when the file doesn't exist.
     */
    public function delete(string $path): void;
}
