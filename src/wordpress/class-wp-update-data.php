<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\WordPress;

use ScottOffen\WpGithubUpdater\Internal\Platform\UpdateData;

/**
 * The implementation of the `UpdateData` interface for the
 * `delete_site_transient()` function.
 *
 * @internal
 */
final class WpUpdateData implements UpdateData
{
    /**
     * Deletes the `update_plugins` site transient.
     */
    public function forgetPlugins(): void
    {
        \delete_site_transient('update_plugins');
    }

    /**
     * Deletes the `update_themes` site transient.
     */
    public function forgetThemes(): void
    {
        \delete_site_transient('update_themes');
    }
}
