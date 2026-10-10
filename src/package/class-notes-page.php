<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Package;

/**
 * Builds the page that shows the release notes of a theme update.
 *
 * WordPress opens the details link of a theme update in a frame. The page is
 * a whole HTML document with its own small style sheet and no scripts, so it
 * doesn't depend on the admin screen being set up, which `admin-post.php`
 * doesn't do.
 */
final class NotesPage
{
    /**
     * @param string $title The title of the page, such as the theme name and version. It is escaped here.
     * @param string $notesHtml The release notes as safe HTML. It is placed in the page as it is.
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
h5,h6{margin:1.5em 0 .5em;font-size:1.1em}
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
