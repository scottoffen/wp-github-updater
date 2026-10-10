<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Github;

use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;

/**
 * @internal
 */
final class ReleaseListing
{
    /**
     * @param string $outcome One of the `Outcome` codes.
     * @param list<mixed> $releases The decoded releases, newest first in the order
     *     received from GitHub. Empty unless the outcome is `OK`.
     * @param string $etag The ETag to send with the next request.
     * @param int $status The HTTP status, or 0 when no response arrived.
     */
    public function __construct(
        public readonly string $outcome,
        public readonly array $releases = [],
        public readonly string $etag = '',
        public readonly int $status = 0,
    ) {
    }


    public function isOk(): bool
    {
        return $this->outcome === Outcome::OK;
    }
}
