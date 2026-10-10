<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Package;

use ScottOffen\WpGithubUpdater\Platform\Files;
use ScottOffen\WpGithubUpdater\Platform\Hooks;
use ScottOffen\WpGithubUpdater\Platform\Messages;
use ScottOffen\WpGithubUpdater\Platform\UpdateData;
use ScottOffen\WpGithubUpdater\Release\Outcome;
use ScottOffen\WpGithubUpdater\Token\TokenStore;
use ScottOffen\WpGithubUpdater\Update\UpdateChecker;
use stdClass;

/**
 * Lets one plugin update itself from GitHub releases.
 *
 * It answers five WordPress hooks, and each one first checks that the request
 * is about this plugin, so several plugins on one site, even from the same
 * repository, never step on each other. The two upgrader filters come from the
 * base class. The other three are:
 *
 * - The `update_plugins_github.com` filter, at priority 99, tells WordPress the
 *   latest version and where to download it. It runs late so it wins over
 *   another updater that also answered. When this plugin has no release to
 *   offer it answers `false`, which clears whatever the other updater said.
 * - The `plugins_api` filter, at priority 99, fills the details popup from the
 *   stored state only. It never makes a request.
 * - The `admin_head` action prints the stylesheet for the alert boxes, but only
 *   after this plugin has answered the details popup in the same request.
 *
 * Nothing here talks to WordPress directly. Errors come from the messages
 * seam, and the hooks are registered through the hooks seam.
 */
final class PluginUpdater extends AbstractPackageUpdater
{
    /** Whether this request asked for the details popup of this plugin, and got an answer. */
    private bool $answeredPopup = false;

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

    /**
     * Answers `update_plugins_github.com`. A plugin that isn't this one gets
     * the incoming value back untouched.
     */
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

    /**
     * Answers `plugins_api` for the details popup of this plugin.
     */
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
        // The popup needs a download link to show its update button. WordPress
        // never fetches it, because the update itself goes through the filter.
        $info->download_link = $release->assetUrl;
        // Without this the popup links to a WordPress.org page for the slug.
        $info->external = true;

        // The popup asks for its information before it prints its head, so the
        // head can tell that this popup is the one for this plugin.
        $this->answeredPopup = true;

        return $info;
    }

    /**
     * Answers `admin_head`. WordPress drops the colors of the alert boxes from
     * the notes, so the popup of this plugin gets them back from a stylesheet.
     */
    private function printPopupStyles(): void
    {
        if ($this->answeredPopup) {
            echo PopupStyles::html();
        }
    }
}
