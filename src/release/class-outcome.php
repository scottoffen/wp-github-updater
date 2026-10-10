<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Release;

/**
 * The codes for how a request, a check, or a download ended.
 *
 * A code is stored as its value in the stored state, and the error that a code
 * creates has the code `wp_github_updater_` followed by the value.
 *
 * @internal
 */
final class Outcome
{
    /** The request or the download succeeded. */
    public const OK = 'ok';

    /** The response has the status 304 Not Modified. */
    public const NOT_MODIFIED = 'not_modified';

    /**
     * There is no release to use. The list of releases has no release that the
     * resolver can use, or the stored state has no release.
     */
    public const NO_RELEASE = 'no_release';

    /**
     * The highest release doesn't have exactly one asset with a name that
     * matches the asset pattern.
     */
    public const ASSET_MISMATCH = 'asset_mismatch';

    /** The response has the status 401, or a 403 that isn't a rate limit. */
    public const AUTH = 'auth';

    /** The response has the status 404. */
    public const NOT_FOUND = 'not_found';

    /** The response has the status 429, or a 403 with a rate limit header. */
    public const RATE_LIMITED = 'rate_limited';

    /** No response is returned. */
    public const NETWORK = 'network';

    /**
     * The response can't be used. The status is unexpected, the body isn't a
     * JSON list, a redirect isn't followed, a 304 has no stored release to
     * confirm, or the asset of the release lacks details that the updater
     * requires.
     */
    public const BAD_RESPONSE = 'bad_response';

    /**
     * The SHA-256 digest of the downloaded file doesn't match the digest that
     * GitHub reports for the asset.
     */
    public const DIGEST_MISMATCH = 'digest_mismatch';

    /**
     * A temporary file can't be created, or the downloaded file can't be read
     * to compute its digest.
     */
    public const FILE_ERROR = 'file_error';

    /**
     * The address of the asset isn't a GitHub API address for a release asset.
     */
    public const REFUSED = 'refused';

    /** The unpacked update isn't in a folder that is named after the slug. */
    public const FOLDER_MISMATCH = 'folder_mismatch';
}
