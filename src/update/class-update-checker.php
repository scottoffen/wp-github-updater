<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Update;

use ScottOffen\WpGithubUpdater\Internal\Github\GitHubClient;
use ScottOffen\WpGithubUpdater\Internal\Github\ReleaseListing;
use ScottOffen\WpGithubUpdater\Internal\Platform\Clock;
use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;
use ScottOffen\WpGithubUpdater\Internal\Release\Release;
use ScottOffen\WpGithubUpdater\Internal\Release\ReleaseResolver;
use ScottOffen\WpGithubUpdater\Internal\Token\TokenStore;

/**
 * A checker for running an update check against the GitHub API and saving the
 * resulting `ReleaseState` in the cache.
 *
 * @internal
 */
final class UpdateChecker
{
    /**
     * The outcomes that keep the release and the ETag of the previous state.
     */
    private const LEAVES_RELEASE_UNCHANGED = [Outcome::NETWORK, Outcome::RATE_LIMITED, Outcome::BAD_RESPONSE];

    public function __construct(
        private readonly GitHubClient $client,
        private readonly ReleaseResolver $resolver,
        private readonly TokenStore $tokens,
        private readonly ReleaseCache $cache,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Runs an update check that doesn't send the stored ETag.
     *
     * @return ReleaseState The state that the check saved in the cache.
     */
    public function checkNow(): ReleaseState
    {
        return $this->check(true);
    }

    /**
     * Removes the stored state.
     */
    public function clear(): void
    {
        $this->cache->clear();
    }

    /**
     * Returns the release to install.
     *
     * When the stored state is fresh, the method returns the release in it.
     * Otherwise, the method runs an update check, which sends the stored ETag
     * when the stored state has a release, and returns the release in the state
     * that the check saves.
     *
     * @return Release|null The release, or null when that state has no
     *     release.
     */
    public function latest(): ?Release
    {
        if ($this->cache->isFresh()) {
            return $this->cache->state()->release;
        }

        return $this->check(false)->release;
    }

    /**
     * Returns the stored state.
     *
     * @return ReleaseState The state returned by the `state()` method of the
     *     cache.
     */
    public function state(): ReleaseState
    {
        return $this->cache->state();
    }

    /**
     * Runs an update check and saves the resulting state in the cache.
     *
     * The request sends the token from the token store.
     *
     * @param bool $force True to send no ETag. False to send the ETag of the
     *     previous state when the previous state has a release.
     *
     * @return ReleaseState The state that was saved. A listing with the `OK`
     *     outcome gives the result of the `resolved()` method, a listing with
     *     the `NOT_MODIFIED` outcome gives the result of the `unchanged()`
     *     method, and any other outcome gives the result of the `failed()`
     *     method.
     */
    private function check(bool $force): ReleaseState
    {
        $previous = $this->cache->state();
        $etag = !$force && $previous->release !== null ? $previous->etag : null;

        $listing = $this->client->listReleases($this->tokens->get(), $etag);
        $now = $this->clock->now();

        $state = match ($listing->outcome) {
            Outcome::OK => $this->resolved($previous, $listing, $now),
            Outcome::NOT_MODIFIED => $this->unchanged($previous, $listing, $now),
            default => $this->failed($previous, $listing->outcome, $listing->status, $now),
        };

        $this->cache->save($state);

        return $state;
    }

    /**
     * Returns the state for a listing that has the `OK` outcome.
     *
     * @return ReleaseState A state with the `OK` outcome, the status and the
     *     ETag of the listing, and the release from the resolver. When the
     *     resolver returns an `Outcome` code, the result of the `failed()`
     *     method for that code.
     */
    private function resolved(ReleaseState $previous, ReleaseListing $listing, int $now): ReleaseState
    {
        $result = $this->resolver->resolve($listing->releases);

        if ($result instanceof Release) {
            return new ReleaseState($now, Outcome::OK, $listing->status, $listing->etag, $result);
        }

        return $this->failed($previous, $result, $listing->status, $now);
    }

    /**
     * Returns the state for a listing that has the `NOT_MODIFIED` outcome.
     *
     * @return ReleaseState A state with the `OK` outcome, the status of the
     *     listing, and the release of the previous state. The ETag is the ETag
     *     of the listing, or the ETag of the previous state when the listing
     *     has none. When the previous state has no release, the result of the
     *     `failed()` method with the `BAD_RESPONSE` outcome.
     */
    private function unchanged(ReleaseState $previous, ReleaseListing $listing, int $now): ReleaseState
    {
        if ($previous->release === null) {
            return $this->failed($previous, Outcome::BAD_RESPONSE, $listing->status, $now);
        }

        $etag = $listing->etag !== '' ? $listing->etag : $previous->etag;

        return new ReleaseState($now, Outcome::OK, $listing->status, $etag, $previous->release);
    }

    /**
     * Returns the state for an outcome that isn't a success.
     *
     * @return ReleaseState A state with the outcome and the status. When the
     *     outcome is `NETWORK`, `RATE_LIMITED`, or `BAD_RESPONSE`, the state
     *     also has the ETag and the release of the previous state. Otherwise,
     *     it has no ETag and no release.
     */
    private function failed(ReleaseState $previous, string $outcome, int $status, int $now): ReleaseState
    {
        if (in_array($outcome, self::LEAVES_RELEASE_UNCHANGED, true)) {
            return new ReleaseState($now, $outcome, $status, $previous->etag, $previous->release);
        }

        return new ReleaseState($now, $outcome, $status);
    }
}
