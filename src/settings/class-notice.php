<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Settings;

/**
 * @internal
 */
final class Notice
{

    public const SAVED = 'saved';


    public const REMOVED = 'removed';


    public const CHECKED = 'checked';


    public const EMPTY = 'empty';


    public const INVALID = 'invalid';


    public const LOCKED = 'locked';


    public const EXPIRED = 'expired';

    /**
     * @return list<string> Every notice code.
     */
    public static function all(): array
    {
        return [self::SAVED, self::REMOVED, self::CHECKED, self::EMPTY, self::INVALID, self::LOCKED, self::EXPIRED];
    }


    public static function isKnown(string $code): bool
    {
        return in_array($code, self::all(), true);
    }


    public static function isSuccess(string $code): bool
    {
        return in_array($code, [self::SAVED, self::REMOVED, self::CHECKED], true);
    }
}
