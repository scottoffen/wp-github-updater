<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Package;

/**
 * @internal
 */
final class PopupStyles
{

    private const KINDS = [
        'note' => '#0969da',
        'tip' => '#1a7f37',
        'important' => '#8250df',
        'warning' => '#9a6700',
        'caution' => '#cf222e',
    ];


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
