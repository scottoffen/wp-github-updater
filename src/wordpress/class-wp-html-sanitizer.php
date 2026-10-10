<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\WordPress;

use ScottOffen\WpGithubUpdater\Internal\Platform\HtmlSanitizer;

/**
 * @internal
 */
final class WpHtmlSanitizer implements HtmlSanitizer
{
    public function post(string $html): string
    {
        return \wp_kses_post($html);
    }
}
