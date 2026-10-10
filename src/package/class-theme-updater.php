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
 * @internal
 */
final class ThemeUpdater extends AbstractPackageUpdater
{

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


    private function clearState(): void
    {
        $this->checker->clear();
    }


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
