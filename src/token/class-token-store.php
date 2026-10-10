<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Token;

use InvalidArgumentException;
use ScottOffen\WpGithubUpdater\Platform\Constants;
use ScottOffen\WpGithubUpdater\Platform\OptionStore;

/**
 * Finds, saves, and masks the GitHub token for one plugin or theme.
 *
 * The prefix drives both names, so two packages on one site never share a
 * token. For the prefix `fdcrm` the constant is `FDCRM_GITHUB_TOKEN` and the
 * option is `fdcrm_updater_github_token`.
 *
 * A constant wins over a saved token. A constant counts only when it is a
 * non-empty string, because a locked field with no token behind it is a dead
 * end. The token is stored as it is, not encrypted, and the option never
 * autoloads.
 */
final class TokenStore
{
    /** The token comes from a constant the site owner defined. */
    public const SOURCE_CONSTANT = 'constant';

    /** The token comes from the option saved on the settings page. */
    public const SOURCE_SETTINGS = 'settings';

    /** There is no token, which is right for a public repository. */
    public const SOURCE_NONE = 'none';

    /** The shortest token that may show its last four characters. */
    private const MIN_LENGTH_TO_SHOW_TAIL = 12;

    /** Printable ASCII with no spaces, which is safe to send in a header. */
    private const TOKEN_PATTERN = '/^[\x21-\x7e]+$/D';

    /**
     * @param OptionStore $options Holds the saved token.
     * @param Constants $constants Reads the constant.
     * @param string $prefix Lowercase letters, digits, and underscores, starting with a letter.
     *
     * @throws InvalidArgumentException If the prefix isn't in that form.
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

    /** The name of the constant that holds the token, such as `FDCRM_GITHUB_TOKEN`. */
    public function constantName(): string
    {
        return strtoupper($this->prefix) . '_GITHUB_TOKEN';
    }

    /** The name of the option that holds the saved token, such as `fdcrm_updater_github_token`. */
    public function optionName(): string
    {
        return $this->prefix . '_updater_github_token';
    }

    /**
     * @return string|null The token in use, or null when there is none.
     */
    public function get(): ?string
    {
        return $this->fromConstant() ?? $this->fromSettings();
    }

    /**
     * @return string One of the `SOURCE_` constants.
     */
    public function source(): string
    {
        if ($this->fromConstant() !== null) {
            return self::SOURCE_CONSTANT;
        }

        return $this->fromSettings() !== null ? self::SOURCE_SETTINGS : self::SOURCE_NONE;
    }

    /** Whether a constant supplies the token, so the settings page can't change it. */
    public function isLockedByConstant(): bool
    {
        return $this->fromConstant() !== null;
    }

    /**
     * Saves a token to the option.
     *
     * Surrounding whitespace is trimmed. The token must then be printable
     * ASCII with no spaces.
     *
     * @return bool False, with nothing saved, when a constant supplies the
     *     token or the token isn't usable. True when the token was saved.
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
     * Removes the saved token.
     *
     * @return bool False, with nothing removed, when a constant supplies the
     *     token. True otherwise, including when nothing was saved.
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
     * Writes the token in use so it is safe to show: eight bullets, then the
     * last four characters. A token under twelve characters shows only the
     * bullets, because four characters would give away too much of it.
     *
     * @return string The masked token, or an empty string when there is none.
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

    /** Returns the trimmed value when it is a non-empty string, and null otherwise. */
    private function usable(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
