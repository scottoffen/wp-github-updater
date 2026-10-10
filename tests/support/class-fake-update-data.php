<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use ScottOffen\WpGithubUpdater\Platform\UpdateData;

/**
 * Counts how many times the library asked the platform to forget its stored
 * plugin and theme update information.
 */
final class FakeUpdateData implements UpdateData
{
    private int $forgotten = 0;

    private int $themesForgotten = 0;

    public function forgetPlugins(): void
    {
        $this->forgotten++;
    }

    public function forgetThemes(): void
    {
        $this->themesForgotten++;
    }

    /** How many times `forgetThemes` was called. */
    public function themesForgotten(): int
    {
        return $this->themesForgotten;
    }

    /** How many times `forgetPlugins` was called. */
    public function forgotten(): int
    {
        return $this->forgotten;
    }
}
