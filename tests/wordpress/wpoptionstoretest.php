<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\WordPress;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Internal\WordPress\WpOptionStore;
use ScottOffen\WpGithubUpdater\Tests\Support\WpStub;

final class WpOptionStoreTest extends TestCase
{
    protected function setUp(): void
    {
        WpStub::reset();
    }

    public function testAMissingValueGivesTheDefault(): void
    {
        $store = new WpOptionStore();

        $this->assertNull($store->get('missing'));
        $this->assertSame('fallback', $store->get('missing', 'fallback'));
    }

    public function testAStoredValueComesBackAsItWas(): void
    {
        $store = new WpOptionStore();
        $value = ['v' => 1, 'release' => ['tag' => 'v1.0.0'], 'etag' => 'W/"a"'];

        $store->set('fdcrm_updater_release_state', $value);

        $this->assertSame($value, $store->get('fdcrm_updater_release_state'));
    }

    public function testAutoloadIsOffUnlessRequested(): void
    {
        $store = new WpOptionStore();

        $store->set('quiet', 'a');
        $store->set('loud', 'b', true);

        $this->assertFalse(WpStub::$autoload['quiet']);
        $this->assertTrue(WpStub::$autoload['loud']);
    }

    public function testADeletedValueIsGone(): void
    {
        $store = new WpOptionStore();
        $store->set('name', 'value');

        $store->delete('name');

        $this->assertNull($store->get('name'));
        $this->assertArrayNotHasKey('name', WpStub::$options);
    }

    public function testDeletingAMissingValueIsFine(): void
    {
        (new WpOptionStore())->delete('never-stored');

        $this->addToAssertionCount(1);
    }
}
