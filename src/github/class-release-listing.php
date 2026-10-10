<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Github;

use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;

/**
 * The result of a request for the releases of a repository: an `Outcome` code,
 * the decoded releases, the ETag, and the HTTP status.
 *
 * @internal
 */
final class ReleaseListing
{
    /**
     * @param string $outcome One of the `Outcome` codes.
     * @param list<mixed> $releases The decoded releases.
     * @param string $etag The ETag, or an empty string when there is none.
     * @param int $status The HTTP status, or 0 when no response is returned.
     */
    public function __construct(
        public readonly string $outcome,
        public readonly array $releases = [],
        public readonly string $etag = '',
        public readonly int $status = 0,
    ) {
    }

    /**
     * Indicates whether the outcome is `OK`.
     *
     * @return bool True when the outcome is `OK`.
     */
    public function isOk(): bool
    {
        return $this->outcome === Outcome::OK;
    }
}
