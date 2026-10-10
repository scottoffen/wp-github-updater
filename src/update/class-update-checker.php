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
 * @internal
 */
final class UpdateChecker
{

    private const LEAVES_RELEASE_UNCHANGED = [Outcome::NETWORK, Outcome::RATE_LIMITED, Outcome::BAD_RESPONSE];

    public function __construct(
        private readonly GitHubClient $client,
        private readonly ReleaseResolver $resolver,
        private readonly TokenStore $tokens,
        private readonly ReleaseCache $cache,
        private readonly Clock $clock,
    ) {
    }


    public function latest(): ?Release
    {
        if ($this->cache->isFresh()) {
            return $this->cache->state()->release;
        }

        return $this->check(false)->release;
    }

    /**
     * @return ReleaseState The state after the check.
     */
    public function checkNow(): ReleaseState
    {
        return $this->check(true);
    }


    public function state(): ReleaseState
    {
        return $this->cache->state();
    }


    public function clear(): void
    {
        $this->cache->clear();
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


    private function unchanged(ReleaseState $previous, ReleaseListing $listing, int $now): ReleaseState
    {
        if ($previous->release === null) {
            return $this->failed($previous, Outcome::BAD_RESPONSE, $listing->status, $now);
        }

        $etag = $listing->etag !== '' ? $listing->etag : $previous->etag;

        return new ReleaseState($now, Outcome::OK, $listing->status, $etag, $previous->release);
    }


    private function failed(ReleaseState $previous, string $outcome, int $status, int $now): ReleaseState
    {
        if (in_array($outcome, self::LEAVES_RELEASE_UNCHANGED, true)) {
            return new ReleaseState($now, $outcome, $status, $previous->etag, $previous->release);
        }

        return new ReleaseState($now, $outcome, $status);
    }
}
