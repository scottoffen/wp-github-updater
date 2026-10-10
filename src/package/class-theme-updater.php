<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Package;

use ScottOffen\WpGithubUpdater\Platform\Admin;
use ScottOffen\WpGithubUpdater\Platform\Files;
use ScottOffen\WpGithubUpdater\Platform\Hooks;
use ScottOffen\WpGithubUpdater\Platform\Messages;
use ScottOffen\WpGithubUpdater\Platform\UpdateData;
use ScottOffen\WpGithubUpdater\Release\Outcome;
use ScottOffen\WpGithubUpdater\Token\TokenStore;
use ScottOffen\WpGithubUpdater\Update\UpdateChecker;

/**
 * Lets one theme update itself from GitHub releases.
 *
 * It answers the same two upgrader filters as a plugin, from the base class,
 * and two more things of its own:
 *
 * - The `update_themes_github.com` filter, at priority 99, tells WordPress the
 *   latest version, where to download it, and where to read about it. When this
 *   theme has no release to offer it answers `false`, which clears whatever
 *   another updater said. Other themes get the incoming value back untouched.
 * - A handler for `admin-post.php` that shows the release notes. A theme has
 *   no details popup fed by a filter, so WordPress opens the `url` of its
 *   update in a frame, and the `url` points at this handler. The handler is
 *   named with the prefix, so two themes never share one. It shows only what
 *   is in the stored state and makes no request. It needs the same capability
 *   WordPress needs before it shows the link, and it takes no nonce, because it
 *   only reads and the address sits in stored update data for hours.
 *
 * Because a theme's `functions.php` only runs while the theme is active, or is
 * the parent of the active theme, a theme that isn't loaded has no updater
 * answering for it.
 */
final class ThemeUpdater extends AbstractPackageUpdater
{
    /** The capability WordPress requires before it shows the details link of a theme update. */
    private const NOTES_CAPABILITY = 'update_themes';

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

    public function addHooks(): void
    {
        $this->hooks->addFilter('update_themes_github.com', $this->filterUpdate(...), 99, 3);
        $this->hooks->addAction('admin_post_' . $this->notesAction, $this->showNotes(...), 10, 0);
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
     * Answers `update_themes_github.com`. A theme that isn't this one gets the
     * incoming value back untouched.
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
     * Shows the release notes of the stored release, or says there are none.
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
            $body = $this->notes->html($release);
        }

        $this->admin->respond($this->page->html($title, $body));
        $this->admin->terminate();
    }
}
