<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use ScottOffen\WpGithubUpdater\Internal\Platform\UpdateData;

/**
 * A fake `UpdateData` for counting how many times the update data is deleted.
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

    /**
     * Returns the number of calls to the `forgetPlugins()` method.
     *
     * @return int The number of calls.
     */
    public function forgotten(): int
    {
        return $this->forgotten;
    }

    /**
     * Returns the number of calls to the `forgetThemes()` method.
     *
     * @return int The number of calls.
     */
    public function themesForgotten(): int
    {
        return $this->themesForgotten;
    }
}
