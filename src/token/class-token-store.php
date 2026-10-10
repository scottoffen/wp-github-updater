<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Token;

use InvalidArgumentException;
use ScottOffen\WpGithubUpdater\Internal\Platform\Constants;
use ScottOffen\WpGithubUpdater\Internal\Platform\OptionStore;

/**
 * A store for the GitHub token.
 *
 * The token is the trimmed value of the token constant, or else the trimmed
 * value of the saved option. The token constant is the constant that has the
 * name from the `constantName()` method, and the saved option is the option
 * that has the name from the `optionName()` method. A value is used only when
 * it is a string that isn't empty after trimming, and the token constant is in
 * use when its value is used.
 *
 * @internal
 */
final class TokenStore
{
    /** The source when the token is the value of the token constant. */
    public const SOURCE_CONSTANT = 'constant';

    /** The source when the token is the value of the saved option. */
    public const SOURCE_SETTINGS = 'settings';

    /** The source when there is no token. */
    public const SOURCE_NONE = 'none';

    /**
     * The token length from which the masked value ends with the last 4
     * characters of the token.
     */
    private const MIN_LENGTH_TO_SHOW_TAIL = 12;

    /**
     * The pattern for a token that can be saved: one or more printable ASCII
     * characters from `!` to `~`.
     */
    private const TOKEN_PATTERN = '/^[\x21-\x7e]+$/D';

    /**
     * @param OptionStore $options The option store.
     * @param Constants $constants The constant reader.
     * @param string $prefix Lowercase letters, digits, and underscores,
     *     starting with a letter.
     *
     * @throws InvalidArgumentException If the prefix isn't lowercase letters,
     *     digits, and underscores that start with a letter.
     */
    public function __construct(
        private readonly OptionStore $options,
        private readonly Constants $constants,
        private readonly string $prefix,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*$/D', $prefix) !== 1) {
            throw new InvalidArgumentException('The prefix must be lowercase letters, digits, and underscores, starting with a letter.');
        }
    }

    /**
     * Removes the saved token.
     *
     * @return bool True when the token constant isn't in use, and the saved
     *     token is removed if there is one. False when the token constant is in
     *     use, and nothing is removed.
     */
    public function clear(): bool
    {
        if ($this->isLockedByConstant()) {
            return false;
        }

        $this->options->delete($this->optionName());

        return true;
    }

    /**
     * Returns the name of the token constant.
     *
     * @return string The prefix in uppercase followed by `_GITHUB_TOKEN`.
     */
    public function constantName(): string
    {
        return strtoupper($this->prefix) . '_GITHUB_TOKEN';
    }

    /**
     * Returns the token.
     *
     * @return string|null The trimmed value of the token constant, or else the
     *     trimmed value of the saved option, or null when neither is used.
     */
    public function get(): ?string
    {
        return $this->fromConstant() ?? $this->fromSettings();
    }

    /**
     * Indicates whether the token constant is in use.
     *
     * @return bool True when the token constant is defined as a string that
     *     isn't empty after trimming.
     */
    public function isLockedByConstant(): bool
    {
        return $this->fromConstant() !== null;
    }

    /**
     * Returns a masked form of the token.
     *
     * @return string Eight `•` characters, followed by the last 4
     *     characters of the token when the token has 12 or more characters. An
     *     empty string when there is no token.
     */
    public function masked(): string
    {
        $token = $this->get();

        if ($token === null) {
            return '';
        }

        $bullets = str_repeat("\u{2022}", 8);

        return strlen($token) >= self::MIN_LENGTH_TO_SHOW_TAIL ? $bullets . substr($token, -4) : $bullets;
    }

    private function fromConstant(): ?string
    {
        return $this->usable($this->constants->get($this->constantName()));
    }

    private function fromSettings(): ?string
    {
        return $this->usable($this->options->get($this->optionName()));
    }

    /**
     * Returns the name of the option that the saved token is stored in.
     *
     * @return string The prefix followed by `_updater_github_token`.
     */
    public function optionName(): string
    {
        return $this->prefix . '_updater_github_token';
    }

    /**
     * Saves a token in the option.
     *
     * @param string $token The token to save. Leading and trailing whitespace
     *     is removed.
     *
     * @return bool True when the token is saved. False when the token constant
     *     is in use or the trimmed token isn't one or more printable ASCII
     *     characters from `!` to `~`, and nothing is saved.
     */
    public function save(string $token): bool
    {
        $token = trim($token);

        if ($this->isLockedByConstant() || preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            return false;
        }

        $this->options->set($this->optionName(), $token, false);

        return true;
    }

    /**
     * Returns the source of the token.
     *
     * @return string `SOURCE_CONSTANT` when the token constant is in use,
     *     `SOURCE_SETTINGS` when the saved option is in use instead, and
     *     `SOURCE_NONE` when there is no token.
     */
    public function source(): string
    {
        if ($this->fromConstant() !== null) {
            return self::SOURCE_CONSTANT;
        }

        return $this->fromSettings() !== null ? self::SOURCE_SETTINGS : self::SOURCE_NONE;
    }

    /**
     * Determines the token from a value.
     *
     * @param mixed $value The value of the constant or of the option.
     *
     * @return string|null The trimmed value, or null when the value isn't a
     *     string or is empty after trimming.
     */
    private function usable(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
