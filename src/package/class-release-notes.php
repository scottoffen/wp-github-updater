<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Package;

use ScottOffen\MarkdownConverter\MarkdownConverter;
use ScottOffen\WpGithubUpdater\Internal\Platform\HtmlSanitizer;
use ScottOffen\WpGithubUpdater\Internal\Platform\Messages;
use ScottOffen\WpGithubUpdater\Internal\Release\Release;

/**
 * @internal
 */
final class ReleaseNotes
{
    private ?MarkdownConverter $converter = null;

    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
        private readonly Messages $messages,
    ) {
    }

    /**
     * @param Release $release The release whose notes to convert.
     *
     * @return string Safe HTML. A release with no notes, or notes that are
     *     empty after cleaning, gets a short sentence that states there are no
     *     notes.
     */
    public function html(Release $release): string
    {
        if (trim($release->notes) !== '') {
            $html = trim($this->sanitizer->post($this->converter()->toHtml($release->notes)));

            if ($html !== '') {
                return $html;
            }
        }

        return '<p>' . htmlspecialchars($this->messages->noNotes(), ENT_QUOTES, 'UTF-8') . '</p>';
    }

    private function converter(): MarkdownConverter
    {
        return $this->converter ??= new MarkdownConverter(
            imageHosts: ['github.com', '*.githubusercontent.com'],
            html: 'sanitize',
            inlineStyles: true,
            headingOffset: 4,
            referenceLinks: true,
            emoji: true,
        );
    }
}
