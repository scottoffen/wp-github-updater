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
 * What a plugin updater and a theme updater share.
 *
 * That is the public type, the contract that the settings section reads, and
 * the two filters that run while WordPress installs an update. Each filter
 * reads the entry in the extra arguments that identifies the package, so the
 * two work the same for a plugin and for a theme:
 *
 * - `upgrader_pre_download`, at priority 1 so it runs before another updater
 *   can take the download. The address that WordPress has stored can be older
 *   than the state, so it downloads the release in the stored state, whatever
 *   the stored address is.
 * - `upgrader_source_selection`. WordPress installs an update into the folder
 *   named inside the package, so this filter checks that the unpacked update is
 *   in a folder named after the slug.
 *
 * A subclass supplies the hooks that differ, the header to read the name from,
 * and the update information to discard after a forced check.
 *
 * @internal
 */
abstract class AbstractPackageUpdater implements PackageContext, PackageUpdater
{
    /**
     * @param string $slug The name of the install folder.
     * @param string $key What WordPress calls the package in its update data.
     * @param string $headerFile The path of the file with the headers, which is
     *     where the name and the installed version are read from.
     * @param \Closure $settingsFactory Builds the settings section for this
     *     updater when it is first used. It receives the updater.
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
     * Registers the hooks. Calling this twice registers them twice.
     */
    abstract public function addHooks(): void;

    /**
     * The entry in the extra arguments that WordPress passes to an upgrader
     * filter, which identifies the package: `plugin` or `theme`.
     */
    abstract protected function hookExtraKey(): string;

    /** The name of the header that contains the display name. */
    abstract protected function nameHeader(): string;

    /**
     * Makes WordPress discard the stored update information for this kind of
     * package.
     */
    abstract protected function forgetUpdateData(): void;

    public function checkNow(): ReleaseState
    {
        $state = $this->checker->checkNow();

        $this->forgetUpdateData();

        return $state;
    }

    public function state(): ReleaseState
    {
        return $this->checker->state();
    }

    public function settings(): Settings
    {
        return $this->settings ??= ($this->settingsFactory)($this);
    }

    public function tokens(): TokenStore
    {
        return $this->tokens;
    }

    public function tokenConstant(): string
    {
        return $this->tokens->constantName();
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function repository(): string
    {
        return $this->repository;
    }

    /** The capability that the settings section requires. */
    public function capability(): string
    {
        return $this->capability;
    }

    public function installedVersion(): string
    {
        return $this->headers->read($this->head(), 'Version');
    }

    public function name(): string
    {
        $name = $this->headers->read($this->head(), $this->nameHeader());

        return $name !== '' ? $name : $this->slug;
    }

    /**
     * The start of the file with the headers, or an empty string when it can't
     * be read.
     */
    protected function head(): string
    {
        return $this->files->readHead($this->headerFile) ?? '';
    }

    /**
     * Registers the two upgrader filters that work the same for every kind of
     * package.
     */
    protected function addUpgraderHooks(): void
    {
        $this->hooks->addFilter('upgrader_pre_download', $this->filterPreDownload(...), 1, 4);
        $this->hooks->addFilter('upgrader_source_selection', $this->filterSourceSelection(...), 99, 4);
    }

    /**
     * Downloads the update for this package. A download for another package, or
     * a download that another handler already handled, passes through
     * unchanged.
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
     * Rejects an unpacked update that isn't in a folder named after the slug.
     * WordPress passes a failed earlier step to the filter as an error object,
     * and the filter returns it unchanged.
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
     * Whether the extra arguments that WordPress passes to an upgrader filter
     * identify this package. A new install has no entry for the package, so the
     * filter never handles it.
     */
    protected function isOurs(mixed $hookExtra): bool
    {
        return is_array($hookExtra) && ($hookExtra[$this->hookExtraKey()] ?? null) === $this->key;
    }
}
