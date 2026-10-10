<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\WordPress;

use ScottOffen\WpGithubUpdater\Platform\HtmlSanitizer;

/**
 * Cleans HTML with `wp_kses_post`, which allows what WordPress allows in a post.
 */
final class WpHtmlSanitizer implements HtmlSanitizer
{
    public function post(string $html): string
    {
        return \wp_kses_post($html);
    }
}
