<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use ScottOffen\WpGithubUpdater\Internal\Platform\UpdateData;


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


    public function themesForgotten(): int
    {
        return $this->themesForgotten;
    }


    public function forgotten(): int
    {
        return $this->forgotten;
    }
}
