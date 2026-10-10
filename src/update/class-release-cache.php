<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Update;

use InvalidArgumentException;
use ScottOffen\WpGithubUpdater\Internal\Platform\Clock;
use ScottOffen\WpGithubUpdater\Internal\Platform\OptionStore;

/**
 * A cache for reading, saving, and clearing the `ReleaseState` that is stored
 * in an option, and for indicating whether the state is fresh.
 *
 * @internal
 */
final class ReleaseCache
{
    /**
     * The number of seconds after a check that a state with the `OK` outcome is
     * fresh, which is 12 hours.
     */
    public const FRESH_SECONDS = 43200;

    /**
     * The number of seconds after a check that a state with any other outcome
     * is fresh, which is 1 hour.
     */
    public const RETRY_SECONDS = 3600;

    /**
     * @param OptionStore $options The option store.
     * @param Clock $clock The clock.
     * @param string $prefix Lowercase letters, digits, and underscores,
     *     starting with a letter.
     *
     * @throws InvalidArgumentException If the prefix isn't lowercase letters,
     *     digits, and underscores that start with a letter.
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

    /**
     * Removes the option that the state is stored in.
     */
    public function clear(): void
    {
        $this->options->delete($this->optionName());
    }

    /**
     * Indicates whether the stored state is fresh.
     *
     * @return bool True when a check has run and the number of seconds since
     *     the check ended is 0 or more and less than the lifetime. The lifetime
     *     is `FRESH_SECONDS` when the outcome is `OK` and `RETRY_SECONDS`
     *     otherwise.
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

    /**
     * Returns the name of the option that the state is stored in.
     *
     * @return string The prefix followed by `_updater_release_state`.
     */
    public function optionName(): string
    {
        return $this->prefix . '_updater_release_state';
    }

    /**
     * Saves the array that represents a state in the option, with autoload
     * turned off.
     */
    public function save(ReleaseState $state): void
    {
        $this->options->set($this->optionName(), $state->toArray(), false);
    }

    /**
     * Returns the stored state.
     *
     * @return ReleaseState The state returned by the
     *     `ReleaseState::fromArray()` method for the value of the option, or
     *     the state returned by the `ReleaseState::empty()` method when the
     *     `fromArray()` method returns null.
     */
    public function state(): ReleaseState
    {
        return ReleaseState::fromArray($this->options->get($this->optionName())) ?? ReleaseState::empty();
    }
}
