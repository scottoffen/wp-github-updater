<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Token;

use InvalidArgumentException;
use ScottOffen\WpGithubUpdater\Internal\Platform\Constants;
use ScottOffen\WpGithubUpdater\Internal\Platform\OptionStore;

/**
 * @internal
 */
final class TokenStore
{

    public const SOURCE_CONSTANT = 'constant';


    public const SOURCE_SETTINGS = 'settings';


    public const SOURCE_NONE = 'none';


    private const MIN_LENGTH_TO_SHOW_TAIL = 12;


    private const TOKEN_PATTERN = '/^[\x21-\x7e]+$/D';

    /**
     * @param OptionStore $options Stores the saved token.
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


    public function constantName(): string
    {
        return strtoupper($this->prefix) . '_GITHUB_TOKEN';
    }


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


    public function isLockedByConstant(): bool
    {
        return $this->fromConstant() !== null;
    }

    /**
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


    private function usable(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
