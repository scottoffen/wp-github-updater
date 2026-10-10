<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\WordPress;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Tests\Support\WpStub;
use ScottOffen\WpGithubUpdater\WordPress\WpHooks;

final class WpHooksTest extends TestCase
{
    protected function setUp(): void
    {
        WpStub::reset();
    }

    public function testAFilterIsRegisteredWithItsPriorityAndArgumentCount(): void
    {
        $callback = static fn (mixed $value): mixed => $value;

        (new WpHooks())->addFilter('update_plugins_github.com', $callback, 99, 4);

        $this->assertCount(1, WpStub::$hooks);
        $this->assertSame('filter', WpStub::$hooks[0]['type']);
        $this->assertSame('update_plugins_github.com', WpStub::$hooks[0]['hook']);
        $this->assertSame($callback, WpStub::$hooks[0]['callback']);
        $this->assertSame(99, WpStub::$hooks[0]['priority']);
        $this->assertSame(4, WpStub::$hooks[0]['accepted']);
    }

    public function testAnActionIsRegisteredWithItsPriorityAndArgumentCount(): void
    {
        $callback = static function (): void {
        };

        (new WpHooks())->addAction('admin_post_x', $callback, 5, 2);

        $this->assertSame('action', WpStub::$hooks[0]['type']);
        $this->assertSame('admin_post_x', WpStub::$hooks[0]['hook']);
        $this->assertSame($callback, WpStub::$hooks[0]['callback']);
        $this->assertSame(5, WpStub::$hooks[0]['priority']);
        $this->assertSame(2, WpStub::$hooks[0]['accepted']);
    }

    public function testThePriorityAndArgumentCountDefaultToWordPressDefaults(): void
    {
        $hooks = new WpHooks();
        $hooks->addFilter('a', static fn (mixed $v): mixed => $v);
        $hooks->addAction('b', static function (): void {
        });

        foreach (WpStub::$hooks as $entry) {
            $this->assertSame(10, $entry['priority']);
            $this->assertSame(1, $entry['accepted']);
        }
    }
}
