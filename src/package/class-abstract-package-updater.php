<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Package;

use ScottOffen\WpGithubUpdater\Platform\Files;
use ScottOffen\WpGithubUpdater\Platform\Hooks;
use ScottOffen\WpGithubUpdater\Platform\Messages;
use ScottOffen\WpGithubUpdater\Release\Outcome;
use ScottOffen\WpGithubUpdater\Settings\Settings;
use ScottOffen\WpGithubUpdater\Token\TokenStore;
use ScottOffen\WpGithubUpdater\Update\ReleaseState;
use ScottOffen\WpGithubUpdater\Update\UpdateChecker;

/**
 * What a plugin updater and a theme updater share.
 *
 * That is the contract, and the two filters that run while WordPress installs
 * an update, which work the same for both once they know which entry in the
 * extra arguments names the package:
 *
 * - `upgrader_pre_download`, at priority 1 so it runs before another updater
 *   could take the download. It downloads the release in the stored state,
 *   whatever address WordPress holds, because that address can be older than
 *   the state.
 * - `upgrader_source_selection`, which checks that the unpacked update is in a
 *   folder named after the slug, because WordPress installs it into the folder
 *   named inside the package.
 *
 * A subclass supplies the hooks that differ, the header to read the name from,
 * and the update information to discard after a forced check.
 */
abstract class AbstractPackageUpdater implements PackageUpdater
{
    /**
     * @param string $slug The name of the install folder.
     * @param string $key What WordPress calls the package in its update data.
     * @param string $headerFile The path of the file with the headers, which is
     *     where the name and the installed version are read from.
     * @param \Closure $settingsFactory Builds the settings section for this
     *     updater when it is first asked for. It receives the updater.
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
     * Registers the hooks. The builder calls this once.
     */
    abstract public function addHooks(): void;

    /**
     * The entry in the extra arguments WordPress passes to an upgrader filter
     * that names the package: `plugin` or `theme`.
     */
    abstract protected function hookExtraKey(): string;

    /** The name of the header that holds the display name. */
    abstract protected function nameHeader(): string;

    /** Makes WordPress discard its own stored update information for this kind of package. */
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

    /** The capability a person needs to manage the update settings. */
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

    /** The start of the file with the headers, or an empty string when it can't be read. */
    protected function head(): string
    {
        return $this->files->readHead($this->headerFile) ?? '';
    }

    /**
     * Registers the two upgrader filters that work the same for every kind of package.
     */
    protected function addUpgraderHooks(): void
    {
        $this->hooks->addFilter('upgrader_pre_download', $this->filterPreDownload(...), 1, 4);
        $this->hooks->addFilter('upgrader_source_selection', $this->filterSourceSelection(...), 99, 4);
    }

    /**
     * Downloads the update for this package. Another package's download, and a
     * download something else already handled, go through untouched.
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
     * WordPress passes a failed earlier step through as an object, and that
     * goes through untouched too.
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
     * Whether the extra arguments WordPress passes to an upgrader filter name
     * this package. A new install has no entry for it, so it is never ours.
     */
    protected function isOurs(mixed $hookExtra): bool
    {
        return is_array($hookExtra) && ($hookExtra[$this->hookExtraKey()] ?? null) === $this->key;
    }
}
