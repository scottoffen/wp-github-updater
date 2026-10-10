<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Update;

use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;
use ScottOffen\WpGithubUpdater\Internal\Release\Release;

/**
 * The result of an update check: the time that the check ended, the outcome,
 * the HTTP status, the ETag, and the release.
 *
 * The state is written as a plain array that has a schema number. The
 * `fromArray()` method returns null when the value isn't an array in the format
 * that the `toArray()` method writes, which includes an array that has a
 * different schema number.
 *
 * @internal
 */
final class ReleaseState
{
    private const SCHEMA = 1;

    /**
     * @param int $checkedAt The time that the check ended, as a Unix timestamp.
     *     The value 0 means that no check has run.
     * @param string $outcome How the check ended: an `Outcome` code, or an
     *     empty string before any check. Any other string is kept as it is.
     * @param int $status The HTTP status of the response, or 0 when no
     *     response is returned.
     * @param string $etag The ETag of the list of releases that the release
     *     came from, or an empty string when there is none.
     * @param Release|null $release The release to install, or null when there
     *     is none.
     */
    public function __construct(
        public readonly int $checkedAt,
        public readonly string $outcome,
        public readonly int $status = 0,
        public readonly string $etag = '',
        public readonly ?Release $release = null,
    ) {
    }

    /**
     * Returns the state for when no check has run.
     *
     * @return self A state with a `checkedAt` of 0 and an empty outcome.
     */
    public static function empty(): self
    {
        return new self(0, '');
    }

    /**
     * Reads a state from an array.
     *
     * The array must have the keys that the `toArray()` method writes. The `v`
     * value must equal the schema number. The `checked_at` and `status` values
     * must be integers of 0 or more, and the `outcome` and `etag` values must
     * be strings. The `release` value must be null or an array that meets the
     * requirements of the `Release::fromArray()` method.
     *
     * @param mixed $data The value to read.
     *
     * @return self|null The state, or null when the value doesn't meet those
     *     requirements.
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

    /**
     * Indicates whether a check has run.
     *
     * @return bool True when `checkedAt` is greater than 0.
     */
    public function hasChecked(): bool
    {
        return $this->checkedAt > 0;
    }

    /**
     * Indicates whether the check succeeded.
     *
     * @return bool True when the outcome is `OK`.
     */
    public function succeeded(): bool
    {
        return $this->outcome === Outcome::OK;
    }

    /**
     * Returns an array of values that represent the state.
     *
     * @return array<string, mixed> The array that the `fromArray()` method
     *     reads. The `v` value is the schema number, and the `release` value is
     *     the release written as an array, or null when there is no release.
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
}
