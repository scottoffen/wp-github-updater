<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Update;

use InvalidArgumentException;
use ScottOffen\WpGithubUpdater\Platform\Clock;
use ScottOffen\WpGithubUpdater\Platform\OptionStore;

/**
 * Keeps the release state between requests and says when it is too old to use.
 *
 * A state that ended with a release lasts twelve hours. A state that ended any
 * other way lasts one hour, which is how soon a failed check is tried again.
 * The state lives in one option, named from the prefix, that never autoloads.
 */
final class ReleaseCache
{
    /** How long a successful check is trusted, in seconds. */
    public const FRESH_SECONDS = 43200;

    /** How long to wait before trying again after a check that didn't succeed, in seconds. */
    public const RETRY_SECONDS = 3600;

    /**
     * @param OptionStore $options Holds the state.
     * @param Clock $clock Says what time it is.
     * @param string $prefix Lowercase letters, digits, and underscores, starting with a letter.
     *
     * @throws InvalidArgumentException If the prefix isn't in that form.
     */
    public function __construct(
        private readonly OptionStore $options,
        private readonly Clock $clock,
        private readonly string $prefix,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*$/D', $prefix) !== 1) {
            throw new InvalidArgumentException('The prefix must be lowercase letters, digits, and underscores, starting with a letter.');
        }
    }

    /** The name of the option that holds the state, such as `fdcrm_updater_release_state`. */
    public function optionName(): string
    {
        return $this->prefix . '_updater_release_state';
    }

    /**
     * @return ReleaseState The stored state, or an empty state when nothing is
     *     stored or what is stored can't be read.
     */
    public function state(): ReleaseState
    {
        return ReleaseState::fromArray($this->options->get($this->optionName())) ?? ReleaseState::empty();
    }

    /** Stores a state, replacing the one before it. */
    public function save(ReleaseState $state): void
    {
        $this->options->set($this->optionName(), $state->toArray(), false);
    }

    /** Removes the stored state. */
    public function clear(): void
    {
        $this->options->delete($this->optionName());
    }

    /**
     * Whether the stored state is recent enough to use without asking GitHub.
     *
     * A state with no check, or with a check time in the future, is never
     * fresh. A future time means the clock or the stored data is wrong, and
     * trusting it could stop checks for a long time.
     */
    public function isFresh(): bool
    {
        $state = $this->state();

        if (!$state->hasChecked()) {
            return false;
        }

        $age = $this->clock->now() - $state->checkedAt;
        $lifetime = $state->succeeded() ? self::FRESH_SECONDS : self::RETRY_SECONDS;

        return $age >= 0 && $age < $lifetime;
    }
}
