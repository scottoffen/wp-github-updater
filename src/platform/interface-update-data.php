<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Platform;

/**
 * @internal
 */
interface UpdateData
{

    public function forgetPlugins(): void;


    public function forgetThemes(): void;
}
