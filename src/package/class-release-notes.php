<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Package;

use ScottOffen\MarkdownConverter\MarkdownConverter;
use ScottOffen\WpGithubUpdater\Platform\HtmlSanitizer;
use ScottOffen\WpGithubUpdater\Platform\Messages;
use ScottOffen\WpGithubUpdater\Release\Release;

/**
 * Turns the Markdown release notes of a release into HTML for a details popup.
 *
 * The notes can hold text written by people the site owner doesn't know, so
 * the converter runs in its `sanitize` mode, and the result goes through the
 * platform's post sanitizer as a second safety net. The converter has fixed
 * settings and there is no way to supply another, so the output looks the same
 * whichever plugin or theme asks. It is built only when the first notes are
 * converted, because most page loads never show a popup.
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
     * @return string Safe HTML. A release with no notes, or notes that leave
     *     nothing after cleaning, gets a short sentence that says so.
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
