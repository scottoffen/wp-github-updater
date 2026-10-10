<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Package;

use ScottOffen\WpGithubUpdater\Internal\Token\TokenStore;
use ScottOffen\WpGithubUpdater\Internal\Update\ReleaseState;

/**
 * @internal
 */
interface PackageContext
{
    /**
     * @return ReleaseState The state after the check.
     */
    public function checkNow(): ReleaseState;


    public function state(): ReleaseState;


    public function tokens(): TokenStore;


    public function tokenConstant(): string;


    public function installedVersion(): string;
}
