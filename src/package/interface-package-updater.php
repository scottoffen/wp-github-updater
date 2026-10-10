<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Package;

use ScottOffen\WpGithubUpdater\Settings\Settings;
use ScottOffen\WpGithubUpdater\Token\TokenStore;
use ScottOffen\WpGithubUpdater\Update\ReleaseState;

/**
 * What a plugin updater and a theme updater have in common.
 *
 * The settings section reads this contract, so it works the same for both.
 */
interface PackageUpdater
{
    /**
     * Checks for an update now, whatever the cache says.
     *
     * @return ReleaseState What the check left behind.
     */
    public function checkNow(): ReleaseState;

    /**
     * What the last check left behind, without asking for anything new.
     */
    public function state(): ReleaseState;

    /** The token store for this plugin or theme. */
    public function tokens(): TokenStore;

    /** The name of the constant that can hold the token, such as `FDCRM_GITHUB_TOKEN`. */
    public function tokenConstant(): string;

    /** The slug, which is the name of the install folder. */
    public function slug(): string;

    /**
     * The settings section for this plugin or theme. It is the same object
     * every time.
     */
    public function settings(): Settings;

    /** The repository the updates come from, written `owner/name`. */
    public function repository(): string;

    /** The version installed now, read from its header, or an empty string when it can't be read. */
    public function installedVersion(): string;

    /** The display name, read from its header. It is the slug when the header can't be read. */
    public function name(): string;
}
