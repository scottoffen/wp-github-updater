<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\WordPress;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\WordPress\WpConstants;

final class WpConstantsTest extends TestCase
{
    public function testADefinedConstantIsFoundWithItsValue(): void
    {
        if (!defined('WPGU_TEST_DEFINED_TOKEN')) {
            define('WPGU_TEST_DEFINED_TOKEN', 'the-token');
        }

        $constants = new WpConstants();

        $this->assertTrue($constants->has('WPGU_TEST_DEFINED_TOKEN'));
        $this->assertSame('the-token', $constants->get('WPGU_TEST_DEFINED_TOKEN'));
    }

    public function testAConstantWithAFalsyValueIsStillDefined(): void
    {
        if (!defined('WPGU_TEST_EMPTY_TOKEN')) {
            define('WPGU_TEST_EMPTY_TOKEN', '');
        }

        $constants = new WpConstants();

        $this->assertTrue($constants->has('WPGU_TEST_EMPTY_TOKEN'));
        $this->assertSame('', $constants->get('WPGU_TEST_EMPTY_TOKEN'));
    }

    public function testAnUndefinedConstantIsMissingAndGivesNull(): void
    {
        $constants = new WpConstants();

        $this->assertFalse($constants->has('WPGU_TEST_NEVER_DEFINED'));
        $this->assertNull($constants->get('WPGU_TEST_NEVER_DEFINED'));
    }
}
