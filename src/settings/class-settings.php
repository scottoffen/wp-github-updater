<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater;

use InvalidArgumentException;
use ScottOffen\WpGithubUpdater\Internal\Package\PackageContext;
use ScottOffen\WpGithubUpdater\Internal\Platform\Admin;
use ScottOffen\WpGithubUpdater\Internal\Platform\Hooks;
use ScottOffen\WpGithubUpdater\Internal\Settings\Notice;
use ScottOffen\WpGithubUpdater\Internal\Settings\SettingsForm;
use ScottOffen\WpGithubUpdater\Internal\Settings\SettingsModel;
use ScottOffen\WpGithubUpdater\Internal\Settings\SettingsView;
use ScottOffen\WpGithubUpdater\Internal\Token\TokenStore;

/**
 * The updater-provided settings section.
 *
 * The section shows a token field, a status line, and a button that checks for
 * updates. When the token constant is in use, the field is read-only and shows
 * the name of the constant. Otherwise, a Save token button saves the token that
 * is typed in the field, and a Remove token button appears when a token is
 * saved. The section doesn't add a menu item. Call the `render()` method to
 * print the section on a page that already exists, or call the `addPage()`
 * method to add a separate page for it.
 *
 * Each button is a form that posts to `admin-post.php` with the action
 * `<prefix>_updater_save`, `<prefix>_updater_remove`, or
 * `<prefix>_updater_check`. Because the action names start with the prefix,
 * updaters with different prefixes don't share a handler. A handler checks the
 * capability first and then the nonce. It then does its work and redirects back
 * to the referring page with a notice code added to the address. After a token
 * is saved or removed, the handler checks for updates.
 *
 * The `register()` method of the `Updater` class registers the handlers.
 */
final class Settings
{
    private readonly string $tokenField;

    private readonly string $noticeParam;

    private readonly string $pageSlug;

    private bool $hooksAdded = false;

    /**
     * Creates the section for one plugin or theme.
     *
     * @internal
     *
     * @param PackageContext $updater The plugin or theme that the section is
     *     for.
     * @param Admin $admin The admin functions.
     * @param SettingsView $view The view of the section.
     * @param Hooks $hooks The hook system.
     * @param string $prefix The prefix of the plugin or theme. It sets the
     *     names of the actions, the token field, the notice parameter, and the
     *     page slug.
     * @param string $capability The capability that is required to see and use
     *     the section.
     */
    public function __construct(
        private readonly PackageContext $updater,
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
     * Registers the three handlers that the forms of the section post to. This
     * method is idempotent; calling it multiple times has no additional effect.
     *
     * @internal
     */
    public function addHooks(): void
    {
        if ($this->hooksAdded) {
            return;
        }

        $this->hooksAdded = true;

        $this->hooks->addAction('admin_post_' . $this->action('save'), $this->handleSave(...), 10, 0);
        $this->hooks->addAction('admin_post_' . $this->action('remove'), $this->handleRemove(...), 10, 0);
        $this->hooks->addAction('admin_post_' . $this->action('check'), $this->handleCheck(...), 10, 0);
    }

    /**
     * Adds a separate page for the updater-provided settings section to the
     * admin menu.
     *
     * The updater adds the page when the `admin_menu` action runs, so call the
     * method before then, such as while the plugin or theme loads. The page
     * requires the capability, and it prints the title in an `h1` element above
     * the section.
     *
     * @param string $title The title of the page and of its menu entry.
     * @param string $parent The slug of the menu to add it under, such as
     *     `options-general.php` for Settings or `themes.php` for Appearance.
     *
     * @throws InvalidArgumentException If the title or the parent is empty or
     *     has only whitespace.
     */
    public function addPage(string $title, string $parent): void
    {
        if (trim($title) === '' || trim($parent) === '') {
            throw new InvalidArgumentException('A page requires a title and a parent menu.');
        }

        // Priority 99 runs it after a parent menu that the host added at the
        // default priority.
        $this->hooks->addAction('admin_menu', function () use ($title, $parent): void {
            $this->admin->addSubmenuPage($parent, $title, $this->capability, $this->pageSlug, function () use ($title): void {
                echo '<div class="wrap"><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>' . $this->html('') . '</div>';
            });
        }, 99, 0);
    }

    /**
     * Prints the updater-provided settings section at the place where it is
     * called.
     *
     * Because the section contains its own forms, call the method outside any
     * `<form>` element. The method prints nothing when the current user doesn't
     * have the capability.
     *
     * @param string $heading A heading to print above the section as an `h2`
     *     element. The default is no heading.
     */
    public function render(string $heading = ''): void
    {
        if (!$this->admin->can($this->capability)) {
            return;
        }

        echo $this->html($heading);
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

    /**
     * Handles the form that saves the token.
     *
     * The `handle()` method makes the capability and nonce checks first. The
     * notice code is `Notice::LOCKED` when the token constant is in use,
     * `Notice::EMPTY` when the field is empty, and `Notice::INVALID` when the
     * token can't be saved. Otherwise, the method saves the token, checks for
     * updates, and the notice code is `Notice::SAVED`.
     */
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

    /**
     * Handles the form that removes the saved token.
     *
     * The `handle()` method makes the capability and nonce checks first. The
     * notice code is `Notice::LOCKED` when the token constant is in use.
     * Otherwise, the method removes the saved token, checks for updates, and
     * the notice code is `Notice::REMOVED`.
     */
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

    /**
     * Handles the form that checks for updates.
     *
     * The `handle()` method makes the capability and nonce checks first. The
     * method then checks for updates, and the notice code is `Notice::CHECKED`.
     */
    private function handleCheck(): void
    {
        $this->handle('check', function (): string {
            $this->updater->checkNow();

            return Notice::CHECKED;
        });
    }

    /**
     * Runs one handler.
     *
     * When the current user doesn't have the capability, the method responds
     * with 403 Forbidden and stops. Otherwise, if the nonce is valid, the
     * method runs `$work` and redirects with the notice code returned by
     * `$work`. If the nonce isn't valid, the method skips `$work` and
     * redirects with the `Notice::EXPIRED` code. A redirect is followed by the
     * end of the request.
     *
     * @param string $name The name of the handler: `save`, `remove`, or
     *     `check`.
     * @param \Closure(): string $work The closure for the work of the handler.
     *     It returns the `Notice` code to redirect with.
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
