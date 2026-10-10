<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Settings;

use InvalidArgumentException;
use ScottOffen\WpGithubUpdater\Package\PackageUpdater;
use ScottOffen\WpGithubUpdater\Platform\Admin;
use ScottOffen\WpGithubUpdater\Platform\Hooks;
use ScottOffen\WpGithubUpdater\Token\TokenStore;

/**
 * The settings section a plugin or theme embeds in its own settings page.
 *
 * The section has a token field, a button that checks for updates now, and a
 * status line. The library adds no menu items on its own. A package shows the
 * section with `render` on a page it already has, outside any `<form>`, or
 * asks for a page of its own with `addPage`.
 *
 * Each button is a small form that posts to `admin-post.php`, with an action
 * named from the prefix, so two packages never share a handler. A handler
 * checks the capability first and then the nonce. It does its work, and
 * redirects back to the page the form came from with a notice code in the
 * address. Saving or removing a token checks for updates right away.
 *
 * The handlers are registered when the package registers, because a form post
 * only runs that code. Rendering and adding a page can wait until they are
 * asked for.
 */
final class Settings
{
    private readonly string $tokenField;

    private readonly string $noticeParam;

    private readonly string $pageSlug;

    /**
     * @param PackageUpdater $updater The plugin or theme the section is for.
     * @param string $prefix The prefix of the plugin or theme.
     * @param string $capability The capability needed to see and use the section.
     */
    public function __construct(
        private readonly PackageUpdater $updater,
        private readonly Admin $admin,
        private readonly SettingsView $view,
        private readonly Hooks $hooks,
        private readonly string $prefix,
        private readonly string $capability,
    ) {
        $this->tokenField = $prefix . '_updater_token';
        $this->noticeParam = $prefix . '_updater_notice';
        $this->pageSlug = $prefix . '_updater_settings';
    }

    /**
     * Registers the three handlers the section's forms post to. The builder
     * calls this once.
     */
    public function addHooks(): void
    {
        $this->hooks->addAction('admin_post_' . $this->action('save'), $this->handleSave(...), 10, 0);
        $this->hooks->addAction('admin_post_' . $this->action('remove'), $this->handleRemove(...), 10, 0);
        $this->hooks->addAction('admin_post_' . $this->action('check'), $this->handleCheck(...), 10, 0);
    }

    /**
     * Prints the section. Someone without the capability sees nothing.
     *
     * @param string $heading A heading for the section, or nothing for none.
     */
    public function render(string $heading = ''): void
    {
        if (!$this->admin->can($this->capability)) {
            return;
        }

        echo $this->html($heading);
    }

    /**
     * Adds a page of its own for the section to the admin menu.
     *
     * @param string $title The title of the page and of its menu entry.
     * @param string $parent The slug of the menu to add it under, such as
     *     `options-general.php` for Settings or `themes.php` for Appearance.
     *
     * @throws InvalidArgumentException If the title or the parent is empty.
     */
    public function addPage(string $title, string $parent): void
    {
        if (trim($title) === '' || trim($parent) === '') {
            throw new InvalidArgumentException('A page needs a title and a parent menu.');
        }

        // Priority 99 puts it after a parent menu that the host added at the default priority.
        $this->hooks->addAction('admin_menu', function () use ($title, $parent): void {
            $this->admin->addSubmenuPage($parent, $title, $this->capability, $this->pageSlug, function () use ($title): void {
                echo '<div class="wrap"><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>' . $this->html('') . '</div>';
            });
        }, 99, 0);
    }

    private function action(string $name): string
    {
        return $this->prefix . '_updater_' . $name;
    }

    private function html(string $heading): string
    {
        $tokens = $this->updater->tokens();
        $state = $this->updater->state();

        return $this->view->render(new SettingsModel(
            $heading,
            $state,
            $this->updater->installedVersion(),
            $state->hasChecked() ? $this->admin->dateTime($state->checkedAt) : '',
            $tokens->isLockedByConstant(),
            $this->updater->tokenConstant(),
            $tokens->source() === TokenStore::SOURCE_SETTINGS ? $tokens->masked() : '',
            $this->admin->query($this->noticeParam),
            $this->tokenField,
            $this->form('save'),
            $this->form('remove'),
            $this->form('check'),
        ));
    }

    private function form(string $name): SettingsForm
    {
        $action = $this->action($name);

        return new SettingsForm($this->admin->postUrl($action), $this->admin->nonceFields($action));
    }

    private function handleSave(): void
    {
        $this->handle('save', function (): string {
            $tokens = $this->updater->tokens();

            if ($tokens->isLockedByConstant()) {
                return Notice::LOCKED;
            }

            $token = trim($this->admin->input($this->tokenField));

            if ($token === '') {
                return Notice::EMPTY;
            }

            if (!$tokens->save($token)) {
                return Notice::INVALID;
            }

            $this->updater->checkNow();

            return Notice::SAVED;
        });
    }

    private function handleRemove(): void
    {
        $this->handle('remove', function (): string {
            $tokens = $this->updater->tokens();

            if ($tokens->isLockedByConstant()) {
                return Notice::LOCKED;
            }

            $tokens->clear();
            $this->updater->checkNow();

            return Notice::REMOVED;
        });
    }

    private function handleCheck(): void
    {
        $this->handle('check', function (): string {
            $this->updater->checkNow();

            return Notice::CHECKED;
        });
    }

    /**
     * Runs one handler. The capability comes first, and a person without it
     * is refused outright. Then the nonce, and a form that expired goes back
     * with a notice and does nothing else.
     *
     * @param \Closure(): string $work Does the work and returns the notice code.
     */
    private function handle(string $name, \Closure $work): void
    {
        if (!$this->admin->can($this->capability)) {
            $this->admin->deny();

            return;
        }

        $notice = $this->admin->verifyNonce($this->action($name)) ? $work() : Notice::EXPIRED;

        $this->admin->redirectBack($this->noticeParam, $notice);
        $this->admin->terminate();
    }
}
