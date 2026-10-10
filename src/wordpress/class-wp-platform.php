<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\WordPress;

use ScottOffen\WpGithubUpdater\Platform\Platform;

/**
 * Builds the platform for a WordPress site.
 */
final class WpPlatform
{
    public static function create(): Platform
    {
        return new Platform(
            new WpClock(),
            new WpOptionStore(),
            new WpConstants(),
            new WpFiles(),
            new WpHooks(),
            new WpHttpClient(),
            new WpHtmlSanitizer(),
            new WpUpdateData(),
            new WpMessages(),
            new WpAdmin(),
            new WpSettingsText(),
        );
    }
}
