<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Package;

/**
 * An HTML page for showing a title and release notes.
 *
 * @internal
 */
final class NotesPage
{
    /**
     * Returns the HTML document for a title and release notes.
     *
     * @param string $title The title of the page. It is escaped with the
     *     `htmlspecialchars()` function before it is used.
     * @param string $notesHtml The HTML for the release notes. It is placed in
     *     the page without being escaped.
     *
     * @return string The HTML document, with the title in the `title` element
     *     and in an `h1` element that is followed by the release notes. The
     *     document has a `robots` meta element with the value
     *     `noindex, nofollow`.
     */
    public function html(string $title, string $notesHtml): string
    {
        $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{$title}</title>
<style>
body{margin:0;padding:16px 24px;font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#1d2327;background:#fff}
h1{margin:0 0 .75em;font-size:1.5em}
h3,h4,h5,h6{margin:1.5em 0 .5em;font-size:1.1em}
a{color:#2271b1}
img{max-width:100%;height:auto}
pre,code{font-family:Consolas,Monaco,monospace;font-size:.95em}
pre{padding:8px;overflow:auto;background:#f6f7f7}
table{border-collapse:collapse}
</style>
</head>
<body>
<h1>{$title}</h1>
{$notesHtml}
</body>
</html>
HTML;
    }
}
