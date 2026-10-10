<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\WordPress;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Internal\WordPress\WpAdmin;
use ScottOffen\WpGithubUpdater\Internal\WordPress\WpClock;
use ScottOffen\WpGithubUpdater\Internal\WordPress\WpConstants;
use ScottOffen\WpGithubUpdater\Internal\WordPress\WpFiles;
use ScottOffen\WpGithubUpdater\Internal\WordPress\WpHooks;
use ScottOffen\WpGithubUpdater\Internal\WordPress\WpHtmlSanitizer;
use ScottOffen\WpGithubUpdater\Internal\WordPress\WpHttpClient;
use ScottOffen\WpGithubUpdater\Internal\WordPress\WpMessages;
use ScottOffen\WpGithubUpdater\Internal\WordPress\WpOptionStore;
use ScottOffen\WpGithubUpdater\Internal\WordPress\WpPlatform;
use ScottOffen\WpGithubUpdater\Internal\WordPress\WpSettingsText;
use ScottOffen\WpGithubUpdater\Internal\WordPress\WpUpdateData;

final class WpPlatformTest extends TestCase
{
    public function testItBuildsThePlatformFromTheWordPressImplementations(): void
    {
        $platform = WpPlatform::create();

        $this->assertInstanceOf(WpClock::class, $platform->clock);
        $this->assertInstanceOf(WpOptionStore::class, $platform->options);
        $this->assertInstanceOf(WpConstants::class, $platform->constants);
        $this->assertInstanceOf(WpFiles::class, $platform->files);
        $this->assertInstanceOf(WpHooks::class, $platform->hooks);
        $this->assertInstanceOf(WpHttpClient::class, $platform->http);
        $this->assertInstanceOf(WpHtmlSanitizer::class, $platform->htmlSanitizer);
        $this->assertInstanceOf(WpUpdateData::class, $platform->updateData);
        $this->assertInstanceOf(WpMessages::class, $platform->messages);
        $this->assertInstanceOf(WpAdmin::class, $platform->admin);
        $this->assertInstanceOf(WpSettingsText::class, $platform->settingsText);
    }
}
