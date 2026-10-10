<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Settings;

/**
 * The fixed list of notices the settings section can show.
 *
 * A handler redirects back to the settings page with one of these codes in the
 * address. The page shows a notice only for a code on this list, and the
 * text comes from the code, never from the address.
 */
final class Notice
{
    /** The token was saved and the updates were checked. */
    public const SAVED = 'saved';

    /** The token was removed and the updates were checked. */
    public const REMOVED = 'removed';

    /** The updates were checked. */
    public const CHECKED = 'checked';

    /** The token field was empty, so nothing was saved. */
    public const EMPTY = 'empty';

    /** The token has characters a token can't have, so it wasn't saved. */
    public const INVALID = 'invalid';

    /** A constant supplies the token, so it can't be changed here. */
    public const LOCKED = 'locked';

    /** The form's nonce was no longer valid. */
    public const EXPIRED = 'expired';

    /**
     * @return list<string> Every notice code.
     */
    public static function all(): array
    {
        return [self::SAVED, self::REMOVED, self::CHECKED, self::EMPTY, self::INVALID, self::LOCKED, self::EXPIRED];
    }

    /** Whether a string is one of the notice codes. */
    public static function isKnown(string $code): bool
    {
        return in_array($code, self::all(), true);
    }

    /** Whether a notice reports success. Every other notice reports a problem. */
    public static function isSuccess(string $code): bool
    {
        return in_array($code, [self::SAVED, self::REMOVED, self::CHECKED], true);
    }
}
