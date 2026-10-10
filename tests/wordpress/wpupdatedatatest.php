<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\WordPress;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Internal\WordPress\WpUpdateData;
use ScottOffen\WpGithubUpdater\Tests\Support\WpStub;

final class WpUpdateDataTest extends TestCase
{
    protected function setUp(): void
    {
        WpStub::reset();
    }

    public function testForgettingPluginsDeletesTheUpdatePluginsTransientAndNothingElse(): void
    {
        (new WpUpdateData())->forgetPlugins();

        $this->assertSame(['update_plugins'], WpStub::$transientsDeleted);
    }

    public function testForgettingThemesDeletesTheUpdateThemesTransientAndNothingElse(): void
    {
        (new WpUpdateData())->forgetThemes();

        $this->assertSame(['update_themes'], WpStub::$transientsDeleted);
    }
}
