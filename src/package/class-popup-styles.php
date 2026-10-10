<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Package;

/**
 * The style element for the alert blocks in the changelog section of the plugin
 * information popup.
 *
 * @internal
 */
final class PopupStyles
{
    /** The kinds of alerts with the color associated with the alert. */
    private const KINDS = [
        'note' => '#0969da',
        'tip' => '#1a7f37',
        'important' => '#8250df',
        'warning' => '#9a6700',
        'caution' => '#cf222e',
    ];

    /**
     * The selector for the changelog section of the plugin information popup.
     */
    private const SCOPE = '#section-changelog';

    /**
     * Returns the style element.
     *
     * @return string A `style` element that has the ID
     *     `wp-github-updater-popup`. Its rules apply to the `.markdown-alert`
     *     elements inside the `#section-changelog` element, with a border color
     *     and a first paragraph color for each kind of alert.
     */
    public static function html(): string
    {
        $scope = self::SCOPE;
        $css = $scope . ' .markdown-alert{margin:0 0 16px;padding:0 1em;border-left:4px solid}'
            . $scope . ' .markdown-alert>p:first-child{font-weight:600}';

        foreach (self::KINDS as $kind => $color) {
            $css .= $scope . ' .markdown-alert-' . $kind . '{border-left-color:' . $color . '}'
                . $scope . ' .markdown-alert-' . $kind . '>p:first-child{color:' . $color . '}';
        }

        return '<style id="wp-github-updater-popup">' . $css . '</style>';
    }
}
