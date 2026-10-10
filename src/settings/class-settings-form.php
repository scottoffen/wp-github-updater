<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Settings;

/**
 * What the view needs to draw one of the section's small forms.
 */
final class SettingsForm
{
    /**
     * @param string $url The address the form posts to.
     * @param string $fields The hidden fields that prove the request, as HTML
     *     from the platform. The view places them in the form as they are.
     */
    public function __construct(
        public readonly string $url,
        public readonly string $fields,
    ) {
    }
}
