<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Platform;

/**
 * The update information the platform keeps for itself.
 *
 * The platform holds its own copy of the address to download each update
 * from. After a forced check, that copy can be older than the library's
 * state, so the library asks the platform to forget it and rebuild it.
 */
interface UpdateData
{
    /**
     * Discards the platform's stored plugin update information. The platform
     * rebuilds it the next time it checks for updates.
     */
    public function forgetPlugins(): void;

    /**
     * Discards the platform's stored theme update information. The platform
     * rebuilds it the next time it checks for updates.
     */
    public function forgetThemes(): void;
}
