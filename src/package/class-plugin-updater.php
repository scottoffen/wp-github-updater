<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Package;

use ScottOffen\WpGithubUpdater\Internal\Platform\Files;
use ScottOffen\WpGithubUpdater\Internal\Platform\Hooks;
use ScottOffen\WpGithubUpdater\Internal\Platform\Messages;
use ScottOffen\WpGithubUpdater\Internal\Platform\UpdateData;
use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;
use ScottOffen\WpGithubUpdater\Internal\Token\TokenStore;
use ScottOffen\WpGithubUpdater\Internal\Update\UpdateChecker;
use stdClass;

/**
 * @internal
 */
final class PluginUpdater extends AbstractPackageUpdater
{

    private bool $handledPopup = false;

    public function __construct(
        PluginPackage $package,
        string $repository,
        string $capability,
        UpdateChecker $checker,
        PackageDownloader $downloader,
        TokenStore $tokens,
        ReleaseNotes $notes,
        Files $files,
        HeaderReader $headers,
        Hooks $hooks,
        Messages $messages,
        private readonly UpdateData $updateData,
        \Closure $settingsFactory,
    ) {
        parent::__construct(
            $package->slug,
            $package->key,
            $package->file,
            $repository,
            $capability,
            $checker,
            $downloader,
            $tokens,
            $notes,
            $files,
            $headers,
            $hooks,
            $messages,
            $settingsFactory,
        );
    }

    public function addHooks(): void
    {
        $this->hooks->addFilter('update_plugins_github.com', $this->filterUpdate(...), 99, 3);
        $this->hooks->addFilter('plugins_api', $this->filterPluginsApi(...), 99, 3);
        $this->hooks->addAction('admin_head', $this->printPopupStyles(...), 10, 0);
        $this->addUpgraderHooks();
    }

    protected function hookExtraKey(): string
    {
        return 'plugin';
    }

    protected function nameHeader(): string
    {
        return 'Plugin Name';
    }

    protected function forgetUpdateData(): void
    {
        $this->updateData->forgetPlugins();
    }


    private function filterUpdate(mixed $update, mixed $pluginData, mixed $pluginFile): mixed
    {
        if ($pluginFile !== $this->key) {
            return $update;
        }

        $release = $this->checker->latest();

        if ($release === null) {
            return false;
        }

        return [
            'slug' => $this->slug,
            'version' => $release->version,
            'package' => $release->assetUrl,
        ];
    }


    private function filterPluginsApi(mixed $result, mixed $action, mixed $args): mixed
    {
        if ($action !== 'plugin_information' || !is_object($args) || ($args->slug ?? null) !== $this->slug) {
            return $result;
        }

        $release = $this->checker->state()->release;

        if ($release === null) {
            return $this->messages->error(Outcome::NO_RELEASE);
        }

        $head = $this->head();
        $name = $this->headers->read($head, 'Plugin Name');

        $info = new stdClass();
        $info->name = $name !== '' ? $name : $this->slug;
        $info->slug = $this->slug;
        $info->version = $release->version;
        $info->author = $this->headers->read($head, 'Author');
        $info->homepage = $this->headers->read($head, 'Plugin URI');
        $info->last_updated = $release->publishedAt;
        $info->sections = ['changelog' => $this->notes->html($release)];
        // The popup must have a download link to show its update button. The
        // update itself goes through the filter, so WordPress never fetches the
        // link.
        $info->download_link = $release->assetUrl;
        // Without this the popup links to a WordPress.org page for the slug.
        $info->external = true;

        // The popup requests its information before it prints its head, so the
        // head can determine that this popup is the one for this plugin.
        $this->handledPopup = true;

        return $info;
    }


    private function printPopupStyles(): void
    {
        if ($this->handledPopup) {
            echo PopupStyles::html();
        }
    }
}
