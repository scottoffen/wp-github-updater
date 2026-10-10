<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Package;

use ScottOffen\WpGithubUpdater\Internal\Token\TokenStore;
use ScottOffen\WpGithubUpdater\Internal\Update\ReleaseState;

/**
 * The context of a plugin or a theme: the update check, the stored state, the
 * token store, the name of the token constant, and the installed version.
 *
 * @internal
 */
interface PackageContext
{
    /**
     * Runs an update check that sends no ETag, and then deletes the update data
     * that is stored in WordPress.
     *
     * @return ReleaseState The state that is saved when the check ends.
     */
    public function checkNow(): ReleaseState;

    /**
     * Returns the installed version.
     *
     * @return string The value of the `Version` header of the plugin or theme,
     *     or an empty string when there is no `Version` header.
     */
    public function installedVersion(): string;

    /**
     * Returns the stored state.
     *
     * @return ReleaseState The state that is stored in the option, or the empty
     *     state when no state is stored.
     */
    public function state(): ReleaseState;

    /**
     * Returns the name of the token constant.
     *
     * @return string The prefix in uppercase followed by `_GITHUB_TOKEN`.
     */
    public function tokenConstant(): string;

    /**
     * Returns the token store.
     *
     * @return TokenStore The token store for the prefix of the plugin or theme.
     */
    public function tokens(): TokenStore;
}
