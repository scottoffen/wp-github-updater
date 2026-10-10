<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Settings;

/**
 * The notice codes for the results of the forms in the settings section.
 *
 * A code is added to the address that a form redirects to, as the value of the
 * notice query parameter.
 *
 * @internal
 */
final class Notice
{
    /** The token was saved and the check for updates ran. */
    public const SAVED = 'saved';

    /** The saved token was removed and the check for updates ran. */
    public const REMOVED = 'removed';

    /** The check for updates ran. */
    public const CHECKED = 'checked';

    /** The token field was empty. */
    public const EMPTY = 'empty';

    /**
     * The token isn't one or more printable ASCII characters from `!` to `~`.
     */
    public const INVALID = 'invalid';

    /**
     * The token constant is in use. The token can't be saved or removed.
     */
    public const LOCKED = 'locked';

    /** The nonce isn't valid. */
    public const EXPIRED = 'expired';

    /**
     * Returns every notice code.
     *
     * @return list<string> The codes, in the order `SAVED`, `REMOVED`,
     *     `CHECKED`, `EMPTY`, `INVALID`, `LOCKED`, and `EXPIRED`.
     */
    public static function all(): array
    {
        return [self::SAVED, self::REMOVED, self::CHECKED, self::EMPTY, self::INVALID, self::LOCKED, self::EXPIRED];
    }

    /**
     * Indicates whether a string is a notice code.
     *
     * @return bool True when `$code` is one of the codes returned by the
     *     `all()` method.
     */
    public static function isKnown(string $code): bool
    {
        return in_array($code, self::all(), true);
    }

    /**
     * Indicates whether a notice code is for a success.
     *
     * @return bool True when `$code` is `SAVED`, `REMOVED`, or `CHECKED`.
     */
    public static function isSuccess(string $code): bool
    {
        return in_array($code, [self::SAVED, self::REMOVED, self::CHECKED], true);
    }
}
