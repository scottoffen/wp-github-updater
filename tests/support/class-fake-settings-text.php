<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use ScottOffen\WpGithubUpdater\Platform\SettingsText;

/**
 * Settings text with no translation. Every string is short and different, and
 * the ones that depend on a value or a code carry it, so a test can see which
 * one the view chose without matching real wording.
 */
final class FakeSettingsText implements SettingsText
{
    /**
     * @param string $suffix Text added to the end of every string, so a test can
     *     put markup in all of them and check that the view escapes it.
     */
    public function __construct(private readonly string $suffix = '')
    {
    }

    public function tokenLabel(): string
    {
        return 'Token label' . $this->suffix;
    }

    public function tokenHelp(): string
    {
        return 'Token help' . $this->suffix;
    }

    public function constantHelp(string $constant): string
    {
        return 'Set by ' . $constant . $this->suffix;
    }

    public function saveButton(): string
    {
        return 'Save button' . $this->suffix;
    }

    public function removeButton(): string
    {
        return 'Remove button' . $this->suffix;
    }

    public function checkButton(): string
    {
        return 'Check button' . $this->suffix;
    }

    public function statusLabel(): string
    {
        return 'Status label' . $this->suffix;
    }

    public function neverChecked(): string
    {
        return 'Never checked' . $this->suffix;
    }

    public function lastChecked(string $when): string
    {
        return 'Last checked ' . $when . $this->suffix;
    }

    public function installed(string $version): string
    {
        return 'Installed ' . $version . $this->suffix;
    }

    public function latest(string $version): string
    {
        return 'Latest ' . $version . $this->suffix;
    }

    public function noneFound(): string
    {
        return 'None found' . $this->suffix;
    }

    public function problem(string $outcome): string
    {
        return 'Problem ' . $outcome . $this->suffix;
    }

    public function notice(string $code): string
    {
        return 'Notice ' . $code . $this->suffix;
    }
}
