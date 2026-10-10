<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\WordPress;

use ScottOffen\WpGithubUpdater\Internal\Platform\UpdateData;

/**
 * @internal
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
