<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Update;

use ScottOffen\WpGithubUpdater\Github\GitHubClient;
use ScottOffen\WpGithubUpdater\Github\ReleaseListing;
use ScottOffen\WpGithubUpdater\Platform\Clock;
use ScottOffen\WpGithubUpdater\Release\Outcome;
use ScottOffen\WpGithubUpdater\Release\Release;
use ScottOffen\WpGithubUpdater\Release\ReleaseResolver;
use ScottOffen\WpGithubUpdater\Token\TokenStore;

/**
 * Decides when to ask GitHub, and keeps what it learns in the cache.
 *
 * `latest` answers from the cache while the cache is fresh, so the update
 * filter costs nothing most of the time. When the cache is old, it asks GitHub
 * with the stored ETag, and a 304 costs no rate limit. `checkNow` skips the
 * cache and sends no ETag, so the answer is always a full fresh one.
 *
 * What a check leaves behind depends on how it ended:
 *
 * - A found release is stored with its ETag.
 * - A 304 keeps the stored release and ETag and only moves the check time.
 * - A check that got no trustworthy answer keeps the stored release and ETag.
 *   That means a network failure, a rate limit, or an answer the updater can't
 *   read, such as a server error. Nothing is known to be wrong with the
 *   release, and an outage shouldn't hide an update.
 * - Every other outcome removes the release and the ETag, because GitHub gave
 *   a clear answer and there is nothing the site can use. That means a token
 *   that no longer works, a repository that isn't found, no stable release,
 *   and a newest release whose asset doesn't match.
 *
 * After any check that didn't end with a release, the next check is an hour
 * away. Releases are never kept past a clear answer, so a bad newest release
 * can't bring back an older one.
 */
final class UpdateChecker
{
    /** The outcomes that keep the stored release, because they say nothing about the release itself. */
    private const KEEPS_RELEASE = [Outcome::NETWORK, Outcome::RATE_LIMITED, Outcome::BAD_RESPONSE];

    public function __construct(
        private readonly GitHubClient $client,
        private readonly ReleaseResolver $resolver,
        private readonly TokenStore $tokens,
        private readonly ReleaseCache $cache,
        private readonly Clock $clock,
    ) {
    }

    /**
     * The release to offer, or null when there isn't one.
     *
     * Asks GitHub only when the cache is out of date.
     */
    public function latest(): ?Release
    {
        if ($this->cache->isFresh()) {
            return $this->cache->state()->release;
        }

        return $this->check(false)->release;
    }

    /**
     * Asks GitHub now, whatever the cache says, and sends no ETag.
     *
     * @return ReleaseState What the check left behind.
     */
    public function checkNow(): ReleaseState
    {
        return $this->check(true);
    }

    /**
     * The stored state, without asking GitHub.
     */
    public function state(): ReleaseState
    {
        return $this->cache->state();
    }

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

    private function resolved(ReleaseState $previous, ReleaseListing $listing, int $now): ReleaseState
    {
        $result = $this->resolver->resolve($listing->releases);

        if ($result instanceof Release) {
            return new ReleaseState($now, Outcome::OK, $listing->status, $listing->etag, $result);
        }

        return $this->failed($previous, $result, $listing->status, $now);
    }

    /**
     * A 304 only means something when there is a stored release to confirm. If
     * there isn't one, GitHub answered a question the updater didn't ask.
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
     * Records a check that didn't end with a release. The stored release and
     * ETag stay only when the outcome says nothing about the release itself.
     */
    private function failed(ReleaseState $previous, string $outcome, int $status, int $now): ReleaseState
    {
        if (in_array($outcome, self::KEEPS_RELEASE, true)) {
            return new ReleaseState($now, $outcome, $status, $previous->etag, $previous->release);
        }

        return new ReleaseState($now, $outcome, $status);
    }
}
