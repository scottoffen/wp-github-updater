<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater;

/**
 * The updater for a plugin or a theme, as returned by the `register()` method
 * of the `Updater` class.
 *
 * The class of the returned object is internal and can change in any release,
 * so use only the members of this interface.
 */
interface PackageUpdater
{
    /**
     * Gets the capability that the updater-provided settings section requires.
     *
     * @return string The capability, which is `manage_options` unless the
     *     `capability()` method of the `Updater` class set another one.
     */
    public function capability(): string;

    /**
     * Gets the updater-provided settings section.
     *
     * @return Settings The section for the plugin or theme.
     */
    public function settings(): Settings;
}
