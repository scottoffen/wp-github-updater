<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\WordPress;

use ScottOffen\WpGithubUpdater\Internal\Platform\Platform;

/**
 * A factory for the `Platform` with the WordPress implementation of each
 * dependency.
 *
 * @internal
 */
final class WpPlatform
{
    /**
     * Returns a new `Platform`.
     *
     * @return Platform A `Platform` with a `WpClock`, a `WpOptionStore`, a
     *     `WpConstants`, a `WpFiles`, a `WpHooks`, a `WpHttpClient`, a
     *     `WpHtmlSanitizer`, a `WpUpdateData`, a `WpMessages`, a `WpAdmin`, and
     *     a `WpSettingsText`.
     */
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
