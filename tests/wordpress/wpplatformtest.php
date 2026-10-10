<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\WordPress;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\WordPress\WpAdmin;
use ScottOffen\WpGithubUpdater\WordPress\WpClock;
use ScottOffen\WpGithubUpdater\WordPress\WpConstants;
use ScottOffen\WpGithubUpdater\WordPress\WpFiles;
use ScottOffen\WpGithubUpdater\WordPress\WpHooks;
use ScottOffen\WpGithubUpdater\WordPress\WpHtmlSanitizer;
use ScottOffen\WpGithubUpdater\WordPress\WpHttpClient;
use ScottOffen\WpGithubUpdater\WordPress\WpMessages;
use ScottOffen\WpGithubUpdater\WordPress\WpOptionStore;
use ScottOffen\WpGithubUpdater\WordPress\WpPlatform;
use ScottOffen\WpGithubUpdater\WordPress\WpSettingsText;
use ScottOffen\WpGithubUpdater\WordPress\WpUpdateData;

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
