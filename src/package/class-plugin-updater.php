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
 * An updater for a plugin, with the update data, the plugin information for the
 * plugin information popup, and the styles for the popup.
 *
 * @internal
 */
final class PluginUpdater extends AbstractPackageUpdater
{
    /**
     * True after the `plugins_api` filter returns the information for this
     * plugin.
     */
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

    /**
     * Registers the `update_plugins_github.com` and `plugins_api` filters, the
     * `admin_head` action, and the upgrader filters.
     */
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
     * Returns the update data for this plugin.
     *
     * The release is the value returned by the `latest()` method of the update
     * checker.
     *
     * @param mixed $update The update data from earlier filters, or `false`
     *     when there is none. The method returns it as it is when
     *     `$pluginFile` isn't the key of this plugin.
     * @param mixed $pluginFile The key of a plugin.
     *
     * @return mixed `$update` when `$pluginFile` isn't the key of this plugin.
     *     Otherwise, `false` when there is no release, or an array with the
     *     `slug`, the `version` of the release, and the `package` address of
     *     the release asset.
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
     * Returns the plugin information for the plugin information popup.
     *
     * @param mixed $result The result of earlier filters, or `false` when
     *     there is none. The method returns it as it is unless `$action` is
     *     `plugin_information` and `$args` is an object with the slug of this
     *     plugin.
     * @param mixed $action The name of the action.
     * @param mixed $args The arguments of the request.
     *
     * @return mixed `$result` when the conditions above aren't met. Otherwise,
     *     an error for the `NO_RELEASE` outcome when the stored state has no
     *     release, or an object with the `name`, `slug`, `version`, `author`,
     *     `homepage`, `last_updated`, `sections`, `download_link`, and
     *     `external` values.
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
        $info->sections = ['changelog' => $this->notes->html($release->notes)];
        // The popup shows its update button only when there is a download
        // link. The `upgrader_pre_download` filter ignores the link.
        $info->download_link = $release->assetUrl;
        // Setting `external` removes the link to a WordPress.org page for the
        // slug from the popup.
        $info->external = true;

        // The plugin information is requested before the head of the popup is
        // printed, so this value is set by the time `printPopupStyles()` runs.
        $this->handledPopup = true;

        return $info;
    }

    /**
     * Prints the popup style element when `$handledPopup` is true.
     */
    private function printPopupStyles(): void
    {
        if ($this->handledPopup) {
            echo PopupStyles::html();
        }
    }
}
