<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Package;

use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;

/**
 * @internal
 */
final class DownloadResult
{
    /**
     * @param string|null $path The path of the downloaded file, or null if the download failed.
     * @param string $outcome The outcome code of the download attempt.
     * @param int $status The HTTP status of the last response, or 0 if there was none.
     */
    public function __construct(
        public readonly ?string $path,
        public readonly string $outcome,
        public readonly int $status = 0,
    ) {
    }


    public function isOk(): bool
    {
        return $this->outcome === Outcome::OK && $this->path !== null;
    }
}
