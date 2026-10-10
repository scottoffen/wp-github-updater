<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Package;

use ScottOffen\WpGithubUpdater\Internal\Platform\Files;
use ScottOffen\WpGithubUpdater\Internal\Platform\Hooks;
use ScottOffen\WpGithubUpdater\Internal\Platform\Messages;
use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;
use ScottOffen\WpGithubUpdater\Internal\Token\TokenStore;
use ScottOffen\WpGithubUpdater\Internal\Update\ReleaseState;
use ScottOffen\WpGithubUpdater\Internal\Update\UpdateChecker;
use ScottOffen\WpGithubUpdater\PackageUpdater;
use ScottOffen\WpGithubUpdater\Settings;

/**
 * A base class for the plugin updater and the theme updater.
 *
 * The class implements the `PackageUpdater` and `PackageContext` interfaces and
 * two filters for installing an update. Each filter reads the entry in the
 * extra arguments that identifies the package.
 *
 * The `upgrader_pre_download` filter runs at priority 1. It downloads the
 * release in the stored state and ignores the address that it is passed. The
 * `upgrader_source_selection` filter checks that the unpacked update is in a
 * folder named after the slug.
 *
 * A subclass implements the `addHooks()`, `hookExtraKey()`, `nameHeader()`, and
 * `forgetUpdateData()` methods.
 *
 * @internal
 */
abstract class AbstractPackageUpdater implements PackageContext, PackageUpdater
{
    /**
     * @param string $slug The name of the install folder.
     * @param string $key The key of the package, which the `plugin` or `theme`
     *     entry of the extra arguments is compared with.
     * @param string $headerFile The path of the file to read the `Version`
     *     header and the name header from.
     * @param \Closure $settingsFactory The closure that creates the settings
     *     section. The `settings()` method calls it with this updater on its
     *     first call.
     */
    public function __construct(
        protected readonly string $slug,
        protected readonly string $key,
        protected readonly string $headerFile,
        protected readonly string $repository,
        protected readonly string $capability,
        protected readonly UpdateChecker $checker,
        protected readonly PackageDownloader $downloader,
        protected readonly TokenStore $tokens,
        protected readonly ReleaseNotes $notes,
        protected readonly Files $files,
        protected readonly HeaderReader $headers,
        protected readonly Hooks $hooks,
        protected readonly Messages $messages,
        private readonly \Closure $settingsFactory,
    ) {
    }

    private ?Settings $settings = null;

    /**
     * Registers the hooks.
     */
    abstract public function addHooks(): void;

    public function capability(): string
    {
        return $this->capability;
    }

    public function checkNow(): ReleaseState
    {
        $state = $this->checker->checkNow();

        $this->forgetUpdateData();

        return $state;
    }

    public function installedVersion(): string
    {
        return $this->headers->read($this->head(), 'Version');
    }

    /**
     * Returns the display name.
     *
     * @return string The value of the name header, or the slug when the header
     *     is empty or missing.
     */
    public function name(): string
    {
        $name = $this->headers->read($this->head(), $this->nameHeader());

        return $name !== '' ? $name : $this->slug;
    }

    /**
     * Returns the repository.
     *
     * @return string The repository, written `owner/repository`.
     */
    public function repository(): string
    {
        return $this->repository;
    }

    /**
     * Returns the updater-provided settings section.
     *
     * @return Settings The object that the settings factory creates on the
     *     first call. Every later call returns the same object.
     */
    public function settings(): Settings
    {
        return $this->settings ??= ($this->settingsFactory)($this);
    }

    /**
     * Returns the slug.
     *
     * @return string The name of the install folder.
     */
    public function slug(): string
    {
        return $this->slug;
    }

    public function state(): ReleaseState
    {
        return $this->checker->state();
    }

    public function tokenConstant(): string
    {
        return $this->tokens->constantName();
    }

    public function tokens(): TokenStore
    {
        return $this->tokens;
    }

    /**
     * Returns the key of the entry in the extra arguments of an upgrader filter
     * that identifies the package.
     *
     * @return string The key, such as `plugin` or `theme`.
     */
    abstract protected function hookExtraKey(): string;

    /**
     * Returns the name of the header that the display name is read from.
     *
     * @return string The header name, such as `Plugin Name` or `Theme Name`.
     */
    abstract protected function nameHeader(): string;

    /**
     * Deletes the update data that is stored in WordPress for this kind of
     * package.
     */
    abstract protected function forgetUpdateData(): void;

    /**
     * Returns the first bytes of the file that the headers are read from.
     *
     * @return string The text to search for headers, or an empty string when
     *     the `readHead()` method returns null.
     */
    protected function head(): string
    {
        return $this->files->readHead($this->headerFile) ?? '';
    }

    /**
     * Registers the `upgrader_pre_download` filter at priority 1 and the
     * `upgrader_source_selection` filter at priority 99.
     */
    protected function addUpgraderHooks(): void
    {
        $this->hooks->addFilter('upgrader_pre_download', $this->filterPreDownload(...), 1, 4);
        $this->hooks->addFilter('upgrader_source_selection', $this->filterSourceSelection(...), 99, 4);
    }

    /**
     * Downloads the release in the stored state for this package.
     *
     * @param mixed $reply The reply of an earlier filter, or `false` when no
     *     earlier filter set one. A value other than `false` is returned as it
     *     is, without a download.
     * @param mixed $hookExtra The extra arguments, which identify this package
     *     through the entry for the key returned by the `hookExtraKey()`
     *     method.
     *
     * @return mixed `$reply` when it isn't `false` or `$hookExtra` doesn't
     *     identify this package. Otherwise, the path of the downloaded file, or
     *     an error for the `NO_RELEASE` outcome when the stored state has no
     *     release, or for the outcome of the download.
     */
    protected function filterPreDownload(mixed $reply, mixed $package, mixed $upgrader, mixed $hookExtra): mixed
    {
        if ($reply !== false || !$this->isOurs($hookExtra)) {
            return $reply;
        }

        $release = $this->checker->state()->release;

        if ($release === null) {
            return $this->messages->error(Outcome::NO_RELEASE);
        }

        $result = $this->downloader->download($release);

        return $result->isOk() ? $result->path : $this->messages->error($result->outcome);
    }

    /**
     * Checks the folder of an unpacked update for this package.
     *
     * @param mixed $source The path of the unpacked update, or the result of an
     *     earlier filter, such as a `WP_Error` object. A value that isn't a
     *     string is returned as it is.
     * @param mixed $hookExtra The extra arguments, which identify this package
     *     through the entry for the key returned by the `hookExtraKey()`
     *     method.
     *
     * @return mixed `$source` when it isn't a string, when `$hookExtra` doesn't
     *     identify this package, or when the name of the folder is the slug.
     *     Otherwise, an error for the `FOLDER_MISMATCH` outcome.
     */
    protected function filterSourceSelection(mixed $source, mixed $remoteSource, mixed $upgrader, mixed $hookExtra): mixed
    {
        if (!is_string($source) || !$this->isOurs($hookExtra)) {
            return $source;
        }

        if (basename(rtrim(str_replace('\\', '/', $source), '/')) !== $this->slug) {
            return $this->messages->error(Outcome::FOLDER_MISMATCH);
        }

        return $source;
    }

    /**
     * Indicates whether the extra arguments identify this package.
     *
     * @param mixed $hookExtra The extra arguments.
     *
     * @return bool True when `$hookExtra` is an array and its entry for the key
     *     returned by the `hookExtraKey()` method is equal to the key of this
     *     package.
     */
    protected function isOurs(mixed $hookExtra): bool
    {
        return is_array($hookExtra) && ($hookExtra[$this->hookExtraKey()] ?? null) === $this->key;
    }
}
