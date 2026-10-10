<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Update;

use InvalidArgumentException;
use ScottOffen\WpGithubUpdater\Internal\Platform\Clock;
use ScottOffen\WpGithubUpdater\Internal\Platform\OptionStore;

/**
 * @internal
 */
final class ReleaseCache
{

    public const FRESH_SECONDS = 43200;


    public const RETRY_SECONDS = 3600;

    /**
     * @param OptionStore $options Stores the state.
     * @param Clock $clock Provides the current time.
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


    public function save(ReleaseState $state): void
    {
        $this->options->set($this->optionName(), $state->toArray(), false);
    }


    public function clear(): void
    {
        $this->options->delete($this->optionName());
    }


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
