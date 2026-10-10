<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater;



interface PackageUpdater
{

    public function settings(): Settings;


    public function capability(): string;
}
