<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Platform;

/**
 * A sanitizer for HTML.
 *
 * @internal
 */
interface HtmlSanitizer
{
    /**
     * Removes the tags and attributes that aren't allowed in post content.
     *
     * @param string $html The HTML to sanitize.
     *
     * @return string The HTML without the tags and attributes that aren't
     *     allowed.
     */
    public function post(string $html): string;
}
