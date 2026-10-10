<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Support;

use ScottOffen\WpGithubUpdater\Platform\Platform;

/**
 * A whole fake platform: one of every fake, a plugins folder defined, and a
 * `platform()` method that bundles them for the builder.
 */
final class FakePlatform
{
    public const PLUGIN_DIR = '/wp/wp-content/plugins';

    public readonly FakeClock $clock;

    public readonly FakeOptionStore $options;

    public readonly FakeConstants $constants;

    public readonly FakeFiles $files;

    public readonly FakeHooks $hooks;

    public readonly FakeHttpClient $http;

    public readonly FakeHtmlSanitizer $sanitizer;

    public readonly FakeUpdateData $updateData;

    public readonly FakeMessages $messages;

    public readonly FakeAdmin $admin;

    public readonly FakeSettingsText $settingsText;

    public function __construct()
    {
        $this->clock = new FakeClock();
        $this->options = new FakeOptionStore();
        $this->constants = new FakeConstants();
        $this->files = new FakeFiles();
        $this->hooks = new FakeHooks();
        $this->http = new FakeHttpClient($this->files);
        $this->sanitizer = new FakeHtmlSanitizer();
        $this->updateData = new FakeUpdateData();
        $this->messages = new FakeMessages();
        $this->admin = new FakeAdmin();
        $this->settingsText = new FakeSettingsText();

        $this->constants->define('WP_PLUGIN_DIR', self::PLUGIN_DIR);
    }

    public function platform(): Platform
    {
        return new Platform(
            $this->clock,
            $this->options,
            $this->constants,
            $this->files,
            $this->hooks,
            $this->http,
            $this->sanitizer,
            $this->updateData,
            $this->messages,
            $this->admin,
            $this->settingsText,
        );
    }
}
