<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Github;

use ScottOffen\WpGithubUpdater\Release\Outcome;

/**
 * What GitHub answered when asked for the list of releases.
 *
 * `outcome` is one of the `Outcome` codes. Only `OK` carries releases, and
 * only `OK` and `NOT_MODIFIED` carry an ETag.
 */
final class ReleaseListing
{
    /**
     * @param string $outcome One of the `Outcome` codes.
     * @param list<mixed> $releases The decoded releases, newest first as GitHub
     *     sends them. Empty unless the outcome is `OK`.
     * @param string $etag The ETag to send with the next request. Empty when
     *     there is none.
     * @param int $status The HTTP status, or 0 when no answer arrived. Kept so
     *     the status line can say more than the outcome does.
     */
    public function __construct(
        public readonly string $outcome,
        public readonly array $releases = [],
        public readonly string $etag = '',
        public readonly int $status = 0,
    ) {
    }

    /** Whether the list was read, which means the releases can be resolved. */
    public function isOk(): bool
    {
        return $this->outcome === Outcome::OK;
    }
}
