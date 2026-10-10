<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Package;

/**
 * The stylesheet that gives GitHub's alert boxes their colors back in the
 * details popup of a plugin.
 *
 * The converter colors an alert with `style` attributes. WordPress runs
 * everything in a plugin's popup through a narrow allowlist that keeps `class`
 * on a `div` and drops every `style`, so the colors are lost on the way. The
 * class survives, so a stylesheet in the head of the popup can color the alert
 * by its class instead. The title paragraph loses its class, so the rules reach
 * it as the first paragraph of the alert.
 *
 * The colors are the ones the converter writes for each kind, and a test keeps
 * the two in step. Nothing in the stylesheet comes from a release, so there is
 * nothing to escape.
 */
final class PopupStyles
{
    /** The kinds of alert, and the color the converter gives each. */
    private const KINDS = [
        'note' => '#0969da',
        'tip' => '#1a7f37',
        'important' => '#8250df',
        'warning' => '#9a6700',
        'caution' => '#cf222e',
    ];

    /** The popup puts the changelog in this element, which keeps the rules from reaching anything else. */
    private const SCOPE = '#section-changelog';

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
