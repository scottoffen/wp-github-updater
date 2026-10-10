<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Release;

/**
 * The fixed list of codes that say how an update check or a download ended.
 *
 * A check stores one of these codes and never a message. The settings section
 * turns a code into text when it draws the page, so stored state holds no
 * translated strings. The last four codes come only from a download or an
 * install, so a check never stores them.
 */
final class Outcome
{
    /** The check found a release that can be installed. */
    public const OK = 'ok';

    /** GitHub answered 304, so the stored release is still the latest. */
    public const NOT_MODIFIED = 'not_modified';

    /** None of the releases GitHub returned is a published, stable release. */
    public const NO_RELEASE = 'no_release';

    /** The newest release doesn't have exactly one asset that matches the pattern. */
    public const ASSET_MISMATCH = 'asset_mismatch';

    /** GitHub rejected the token. */
    public const AUTH = 'auth';

    /** GitHub answered 404. For a private repository this also means a missing or wrong token. */
    public const NOT_FOUND = 'not_found';

    /** GitHub refused the request because the rate limit is used up. */
    public const RATE_LIMITED = 'rate_limited';

    /** The request never got an answer. */
    public const NETWORK = 'network';

    /** GitHub answered, but the answer isn't something the updater can read. */
    public const BAD_RESPONSE = 'bad_response';

    /** The downloaded file doesn't match the digest GitHub reported for the asset. */
    public const DIGEST_MISMATCH = 'digest_mismatch';

    /** The file system couldn't create or read the temporary file. */
    public const FILE_ERROR = 'file_error';

    /** The updater won't send a request to the address, so it sent none. */
    public const REFUSED = 'refused';

    /** The unpacked update isn't in a folder named after the plugin or theme. */
    public const FOLDER_MISMATCH = 'folder_mismatch';
}
