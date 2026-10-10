<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Update;

use ScottOffen\WpGithubUpdater\Release\Outcome;
use ScottOffen\WpGithubUpdater\Release\Release;

/**
 * What the last update check left behind: when it ran, how it ended, and the
 * release it found.
 *
 * The state is stored as a plain array with a schema number. `fromArray`
 * returns null for anything it can't read, including a state written by a copy
 * of the library with a different schema, so the caller treats it as empty and
 * checks again.
 *
 * The ETag belongs to the release. A state has an ETag only when it has a
 * release, so a conditional request can only ever confirm a release the state
 * still holds.
 */
final class ReleaseState
{
    private const SCHEMA = 1;

    /**
     * @param int $checkedAt When the last check ended, as a Unix timestamp. 0 means no check has run.
     * @param string $outcome How the last check ended. An `Outcome` code, or an
     *     empty string before any check. A code this version doesn't know is
     *     kept as it is.
     * @param int $status The HTTP status of the last answer, or 0 when there
     *     was none.
     * @param string $etag The ETag of the list the release came from. Empty when there is none.
     * @param Release|null $release The latest release, or null when there isn't one to offer.
     */
    public function __construct(
        public readonly int $checkedAt,
        public readonly string $outcome,
        public readonly int $status = 0,
        public readonly string $etag = '',
        public readonly ?Release $release = null,
    ) {
    }

    /** The state before any check has run. */
    public static function empty(): self
    {
        return new self(0, '');
    }

    /** Whether any check has run. */
    public function hasChecked(): bool
    {
        return $this->checkedAt > 0;
    }

    /** Whether the last check ended with a release that can be offered. */
    public function succeeded(): bool
    {
        return $this->outcome === Outcome::OK;
    }

    /**
     * Writes the state as a plain array that is safe to store.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'v' => self::SCHEMA,
            'checked_at' => $this->checkedAt,
            'outcome' => $this->outcome,
            'status' => $this->status,
            'etag' => $this->etag,
            'release' => $this->release?->toArray(),
        ];
    }

    /**
     * Reads a state written by `toArray`.
     *
     * @param mixed $data The stored value.
     *
     * @return self|null The state, or null when the value isn't a state this
     *     version can read.
     */
    public static function fromArray(mixed $data): ?self
    {
        if (!is_array($data) || ($data['v'] ?? null) !== self::SCHEMA || !array_key_exists('release', $data)) {
            return null;
        }

        foreach (['checked_at', 'status'] as $key) {
            if (!isset($data[$key]) || !is_int($data[$key]) || $data[$key] < 0) {
                return null;
            }
        }

        foreach (['outcome', 'etag'] as $key) {
            if (!isset($data[$key]) || !is_string($data[$key])) {
                return null;
            }
        }

        $release = null;

        if ($data['release'] !== null) {
            $release = Release::fromArray($data['release']);

            if ($release === null) {
                return null;
            }
        }

        return new self($data['checked_at'], $data['outcome'], $data['status'], $data['etag'], $release);
    }
}
