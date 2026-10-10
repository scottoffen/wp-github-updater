<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use ScottOffen\WpGithubUpdater\Internal\Platform\SettingsText;

/**
 * A fake `SettingsText` for returning fixed texts that end with a suffix.
 */
final class FakeSettingsText implements SettingsText
{
    /**
     * @param string $suffix The text added to the end of every string. A test
     *     can include markup in it to check that the view escapes it.
     */
    public function __construct(private readonly string $suffix = '')
    {
    }

    public function checkButton(): string
    {
        return 'Check button' . $this->suffix;
    }

    public function constantHelp(string $constant): string
    {
        return 'Set by ' . $constant . $this->suffix;
    }

    public function installed(string $version): string
    {
        return 'Installed ' . $version . $this->suffix;
    }

    public function lastChecked(string $when): string
    {
        return 'Last checked ' . $when . $this->suffix;
    }

    public function latest(string $version): string
    {
        return 'Latest ' . $version . $this->suffix;
    }

    public function neverChecked(): string
    {
        return 'Never checked' . $this->suffix;
    }

    public function noneFound(): string
    {
        return 'None found' . $this->suffix;
    }

    public function notice(string $code): string
    {
        return 'Notice ' . $code . $this->suffix;
    }

    public function problem(string $outcome): string
    {
        return 'Problem ' . $outcome . $this->suffix;
    }

    public function removeButton(): string
    {
        return 'Remove button' . $this->suffix;
    }

    public function saveButton(): string
    {
        return 'Save button' . $this->suffix;
    }

    public function statusLabel(): string
    {
        return 'Status label' . $this->suffix;
    }

    public function tokenHelp(): string
    {
        return 'Token help' . $this->suffix;
    }

    public function tokenLabel(): string
    {
        return 'Token label' . $this->suffix;
    }
}
