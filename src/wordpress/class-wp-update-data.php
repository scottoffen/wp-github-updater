<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\WordPress;

use ScottOffen\WpGithubUpdater\Platform\UpdateData;

/**
 * Discards the `update_plugins` and `update_themes` site transients.
 * WordPress rebuilds each one the next time something asks it to check.
 */
final class WpUpdateData implements UpdateData
{
    public function forgetPlugins(): void
    {
        \delete_site_transient('update_plugins');
    }

    public function forgetThemes(): void
    {
        \delete_site_transient('update_themes');
    }
}
