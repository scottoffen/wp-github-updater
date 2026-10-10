<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Internal\Package;

use ScottOffen\WpGithubUpdater\Internal\Platform\Admin;
use ScottOffen\WpGithubUpdater\Internal\Platform\Files;
use ScottOffen\WpGithubUpdater\Internal\Platform\Hooks;
use ScottOffen\WpGithubUpdater\Internal\Platform\Messages;
use ScottOffen\WpGithubUpdater\Internal\Platform\UpdateData;
use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;
use ScottOffen\WpGithubUpdater\Internal\Token\TokenStore;
use ScottOffen\WpGithubUpdater\Internal\Update\UpdateChecker;

/**
 * An updater for a theme, with the update data and a page for the release
 * notes.
 *
 * @internal
 */
final class ThemeUpdater extends AbstractPackageUpdater
{
    /** The capability that the release notes page requires. */
    private const NOTES_CAPABILITY = 'update_themes';

    /**
     * @param string $notesAction The name of the action for the release notes
     *     page, which follows `admin_post_` in the name of the hook.
     */
    public function __construct(
        ThemePackage $package,
        string $repository,
        string $capability,
        private readonly string $notesAction,
        UpdateChecker $checker,
        PackageDownloader $downloader,
        TokenStore $tokens,
        ReleaseNotes $notes,
        Files $files,
        HeaderReader $headers,
        Hooks $hooks,
        Messages $messages,
        private readonly UpdateData $updateData,
        private readonly Admin $admin,
        private readonly NotesPage $page,
        \Closure $settingsFactory,
    ) {
        parent::__construct(
            $package->slug,
            $package->key,
            $package->styleFile,
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
     * Registers the `update_themes_github.com` filter, the `admin_post_`
     * action for the release notes page, the `switch_theme` action, and the
     * upgrader filters.
     */
    public function addHooks(): void
    {
        $this->hooks->addFilter('update_themes_github.com', $this->filterUpdate(...), 99, 3);
        $this->hooks->addAction('admin_post_' . $this->notesAction, $this->showNotes(...), 10, 0);
        $this->hooks->addAction('switch_theme', $this->clearState(...), 10, 0);
        $this->addUpgraderHooks();
    }

    protected function hookExtraKey(): string
    {
        return 'theme';
    }

    protected function nameHeader(): string
    {
        return 'Theme Name';
    }

    protected function forgetUpdateData(): void
    {
        $this->updateData->forgetThemes();
    }

    /**
     * Returns the update data for this theme.
     *
     * The release is the value returned by the `latest()` method of the update
     * checker.
     *
     * @param mixed $update The update data from earlier filters, or `false`
     *     when there is none. The method returns it as it is when
     *     `$stylesheet` isn't the key of this theme.
     * @param mixed $stylesheet The key of a theme.
     *
     * @return mixed `$update` when `$stylesheet` isn't the key of this theme.
     *     Otherwise, `false` when there is no release, or an array with the
     *     `theme`, the `version` of the release, the `package` address of the
     *     release asset, and the `url` of the release notes page.
     */
    private function filterUpdate(mixed $update, mixed $themeData, mixed $stylesheet): mixed
    {
        if ($stylesheet !== $this->key) {
            return $update;
        }

        $release = $this->checker->latest();

        if ($release === null) {
            return false;
        }

        return [
            'theme' => $this->slug,
            'version' => $release->version,
            'package' => $release->assetUrl,
            'url' => $this->admin->postUrl($this->notesAction),
        ];
    }

    /**
     * Deletes the stored state.
     *
     * The `addHooks()` method registers this method for the `switch_theme`
     * action. The saved token isn't deleted.
     */
    private function clearState(): void
    {
        $this->checker->clear();
    }

    /**
     * Responds with the release notes page for this theme and ends the request.
     *
     * When the current user doesn't have the `update_themes` capability, the
     * method denies the request and returns without the page. The title is the
     * name of the theme followed by the version of the release in the stored
     * state, and the body is the release notes as HTML. When the stored state
     * has no release, the title is the name of the theme and the body is the
     * message for the `NO_RELEASE` outcome.
     */
    private function showNotes(): void
    {
        if (!$this->admin->can(self::NOTES_CAPABILITY)) {
            $this->admin->deny();

            return;
        }

        $release = $this->checker->state()->release;
        $name = $this->name();

        if ($release === null) {
            $title = $name;
            $body = '<p>' . htmlspecialchars($this->messages->message(Outcome::NO_RELEASE), ENT_QUOTES, 'UTF-8') . '</p>';
        } else {
            $title = $name . ' ' . $release->version;
            $body = $this->notes->html($release->notes);
        }

        $this->admin->respond($this->page->html($title, $body));
        $this->admin->terminate();
    }
}
