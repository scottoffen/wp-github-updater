<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Package;

use ScottOffen\MarkdownConverter\MarkdownConverter;
use ScottOffen\WpGithubUpdater\Internal\Platform\HtmlSanitizer;
use ScottOffen\WpGithubUpdater\Internal\Platform\Messages;

/**
 * A converter for turning the Markdown release notes of a release into HTML.
 *
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
     * Returns Markdown release notes as HTML.
     *
     * @param string $markdown The release notes, as Markdown.
     *
     * @return string The converted and sanitized HTML, trimmed. When the notes
     *     are empty after trimming, or the sanitized HTML is empty, a `p`
     *     element with the message for no notes, escaped with the
     *     `htmlspecialchars()` function.
     */
    public function html(string $markdown): string
    {
        if (trim($markdown) !== '') {
            $html = trim($this->sanitizer->post($this->converter()->toHtml($markdown)));

            if ($html !== '') {
                return $html;
            }
        }

        return '<p>' . htmlspecialchars($this->messages->noNotes(), ENT_QUOTES, 'UTF-8') . '</p>';
    }

    /**
     * Returns the Markdown converter.
     *
     * @return MarkdownConverter The converter, created on the first call with
     *     these options: images from `github.com` and
     *     `*.githubusercontent.com`, sanitized HTML, inline styles, a heading
     *     offset of 2, reference links, and emoji.
     */
    private function converter(): MarkdownConverter
    {
        return $this->converter ??= new MarkdownConverter(
            imageHosts: ['github.com', '*.githubusercontent.com'],
            html: 'sanitize',
            inlineStyles: true,
            headingOffset: 2,
            referenceLinks: true,
            emoji: true,
        );
    }
}
