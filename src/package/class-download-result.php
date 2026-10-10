<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Package;

use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;

/**
 * The result of a download: the path of the downloaded file, an `Outcome` code,
 * and the HTTP status.
 *
 * @internal
 */
final class DownloadResult
{
    /**
     * @param string|null $path The path of the downloaded file, or null when
     *     there is no file.
     * @param string $outcome An `Outcome` code.
     * @param int $status The HTTP status of the response, or 0 when no
     *     response is returned.
     */
    public function __construct(
        public readonly ?string $path,
        public readonly string $outcome,
        public readonly int $status = 0,
    ) {
    }

    /**
     * Indicates whether the download succeeded.
     *
     * @return bool True when the outcome is `OK` and the path isn't null.
     */
    public function isOk(): bool
    {
        return $this->outcome === Outcome::OK && $this->path !== null;
    }
}
