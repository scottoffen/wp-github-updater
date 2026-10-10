<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Platform;

/**
 * The operations for deleting the update data that is stored in WordPress for
 * plugins and for themes.
 *
 * @internal
 */
interface UpdateData
{
    /**
     * Deletes the update data that is stored in WordPress for plugins.
     */
    public function forgetPlugins(): void;

    /**
     * Deletes the update data that is stored in WordPress for themes.
     */
    public function forgetThemes(): void;
}
