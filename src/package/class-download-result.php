<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Package;

use ScottOffen\WpGithubUpdater\Release\Outcome;

/**
 * How a package download ended.
 */
final class DownloadResult
{
    /**
     * @param string|null $path The path of the downloaded file, or null when the download failed.
     * @param string $outcome `Outcome::OK`, or the `Outcome` code that says why it failed.
     * @param int $status The HTTP status of the last answer, or 0 when there was none.
     */
    public function __construct(
        public readonly ?string $path,
        public readonly string $outcome,
        public readonly int $status = 0,
    ) {
    }

    /** Whether the file was downloaded and checked. */
    public function isOk(): bool
    {
        return $this->outcome === Outcome::OK && $this->path !== null;
    }
}
