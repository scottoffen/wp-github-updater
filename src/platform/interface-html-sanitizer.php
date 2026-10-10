<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Platform;

/**
 * @internal
 */
interface HtmlSanitizer
{
    /**
     * @param string $html The HTML to clean.
     *
     * @return string The HTML with disallowed tags and attributes removed.
     */
    public function post(string $html): string;
}
