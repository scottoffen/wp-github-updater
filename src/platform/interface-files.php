<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Platform;

/**
 * The file operations for reading the head of a file, creating a temporary
 * file, hashing a file, and deleting a file.
 *
 * @internal
 */
interface Files
{
    /**
     * Deletes a file. Does nothing when the file doesn't exist.
     *
     * @param string $path The path of the file.
     */
    public function delete(string $path): void;

    /**
     * Returns the hash of the contents of a file.
     *
     * @param string $path The path of the file.
     * @param string $algorithm The name of the algorithm, such as `sha256`.
     *
     * @return string|null The hash as lowercase hexadecimal, or null when the
     *     file can't be read.
     */
    public function hash(string $path, string $algorithm): ?string;

    /**
     * Returns the first bytes of a file.
     *
     * @param string $path The path of the file.
     * @param int $bytes The maximum number of bytes to read.
     *
     * @return string|null The bytes read, or null when the file can't be read.
     */
    public function readHead(string $path, int $bytes = 8192): ?string;

    /**
     * Creates an empty temporary file.
     *
     * @return string|null The path of the new file, or null when it can't be
     *     created.
     */
    public function tempFile(): ?string;
}
