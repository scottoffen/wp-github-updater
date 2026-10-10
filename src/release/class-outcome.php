<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Release;

/**
 * @internal
 */
final class Outcome
{

    public const OK = 'ok';


    public const NOT_MODIFIED = 'not_modified';


    public const NO_RELEASE = 'no_release';


    public const ASSET_MISMATCH = 'asset_mismatch';


    public const AUTH = 'auth';


    public const NOT_FOUND = 'not_found';


    public const RATE_LIMITED = 'rate_limited';


    public const NETWORK = 'network';


    public const BAD_RESPONSE = 'bad_response';


    public const DIGEST_MISMATCH = 'digest_mismatch';


    public const FILE_ERROR = 'file_error';


    public const REFUSED = 'refused';


    public const FOLDER_MISMATCH = 'folder_mismatch';
}
