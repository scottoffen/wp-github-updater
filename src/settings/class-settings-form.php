<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Settings;

/**
 * The address and the hidden fields of a form in the settings section.
 *
 * @internal
 */
final class SettingsForm
{
    /**
     * @param string $url The address that the form posts to.
     * @param string $fields The HTML for the hidden fields of the form.
     */
    public function __construct(
        public readonly string $url,
        public readonly string $fields,
    ) {
    }
}
