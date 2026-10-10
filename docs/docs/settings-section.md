---
sidebar_position: 4
---

# Updater-Provided Settings Section

This page covers what the updater-provided settings section shows, how to display it on an existing settings page or on a separate page, and how to configure the capability that it requires.

The updater doesn't add a page or a menu item to the admin, so the section appears only where your plugin or theme displays it.

## Section Contents

The section has a status line, a button that checks for updates right away, and a field for an access token. Each button reloads the page with a notice that describes what happened. See [Troubleshooting](./troubleshooting.md) for the notices.

### Status

The status line is in the row labeled Status. Before the first check, it reads "Not checked yet." followed by the installed version. After a check, it gives the time of the last check in the date and time format of the site, then the installed version, and then one of the following.

- The latest version that the check found.
- "No release found.", when the repository has no release that the updater can select.
- A message that describes the problem, when the check did not succeed.

When a check fails after an earlier check found a release, the line shows the version of that release together with the message about the problem. See [Troubleshooting](./troubleshooting.md) for each message and what to do about it.

### Check for Updates Now

The **Check for updates now** button sends a request to GitHub right away, regardless of the stored result. It also makes WordPress rebuild its update list, so a release that you just published or fixed appears on the Updates screen without waiting for the next check. See [How Updates Work](./how-updates-work.md) for the other times that the updater checks.

### Access Token

The **Access token** field is for the token that a private repository requires. A public repository can use a token as well, to raise the rate limit of the GitHub API. See [Private Repositories](./private-repositories.md) for how to create a token.

Enter a token and click **Save token** to save it. The updater trims the spaces around the token, and it rejects an empty value and any token that has spaces inside it or characters that are not printable ASCII.

A saved token shows in the field as a masked placeholder, which is eight bullets followed by the last four characters of the token. A token that is shorter than 12 characters shows only the eight bullets. The **Remove token** button appears only while a token is saved. Saving or removing a token checks for updates right away.

## Show the Section on an Existing Page

The `register()` method returns the updater, and the `settings()` method of the updater returns the section. Call `render()` on the section at the place in your page where it should appear.

```php
use ScottOffen\WpGithubUpdater\Updater;

$updater = Updater::plugin(__FILE__)
    ->fromGitHub('owner/repository')
    ->prefix('exampleplugin')
    ->register();

add_action('admin_menu', static function () use ($updater): void {
    add_options_page('Example Plugin', 'Example Plugin', 'manage_options', 'exampleplugin', static function () use ($updater): void {
        echo '<div class="wrap"><h1>Example Plugin</h1>';

        $updater->settings()->render('Updates');

        echo '</div>';
    });
});
```

Replace `Example Plugin` with the title of your page, and replace `exampleplugin` in the `add_options_page()` call with the menu slug of your page. The argument of `render()` is a heading that appears above the section. Leave it out for no heading.

Each button in the section is its own form, and forms can't be nested, so call `render()` outside any `<form>` element of your page.

## Add a Separate Page

If your plugin or theme has no settings page, the `addPage()` method adds a separate page for the section to the admin menu.

```php
Updater::plugin(__FILE__)
    ->fromGitHub('owner/repository')
    ->prefix('exampleplugin')
    ->register()
    ->settings()
    ->addPage('Example Plugin Updates', 'options-general.php');
```

Replace `Example Plugin Updates` with the title of the page, which is also the label of its menu entry. Replace `options-general.php` with the slug of the admin menu to add the page under. The slug `options-general.php` adds the page under Settings, and `themes.php` adds it under Appearance.

The updater adds the page when WordPress builds the admin menu, so call `addPage()` when the plugin or theme loads. An exception is thrown when the title or the menu slug is empty.

## Capability

The section and its buttons require a capability, which is `manage_options` by default. The `capability()` method of the builder sets a different one. See the WordPress documentation on [roles and capabilities](https://wordpress.org/documentation/article/roles-and-capabilities/) for the capabilities that WordPress defines.

```php
Updater::plugin(__FILE__)
    ->fromGitHub('owner/repository')
    ->prefix('exampleplugin')
    ->capability('update_plugins')
    ->register();
```

Replace `update_plugins` with the capability that you want to require. It can use letters, digits, underscores, and hyphens, and an exception is thrown for an empty value or any other character.

The `render()` method prints nothing for someone who doesn't have the capability. A page that `addPage()` adds can't be opened without the capability. The buttons check the capability as well, so a form that someone posts directly to `admin-post.php` is refused without it. See the [Reference](./reference.md) for the signatures of these methods.
