# WordPress GitHub Updater

Lets a WordPress plugin or theme update itself from the releases of a GitHub repository. It works with public and private repositories and implements the update flow itself, so it needs no other update library.

It requires WordPress 7.1 or higher and PHP 8.1 or higher.

## Install

```bash
composer require scottoffen/wp-github-updater
```

The plugin or theme loads the Composer autoloader, so its release zip needs to include the `vendor` folder.

## Plugin Example

Add an `Update URI` header to the main file of the plugin, then register the updater.

```php
<?php
/**
 * Plugin Name: Example Plugin
 * Version:     1.2.3
 * Update URI:  https://github.com/owner/repository
 */

use ScottOffen\WpGithubUpdater\Updater;

require_once __DIR__ . '/vendor/autoload.php';

Updater::plugin(__FILE__)
    ->fromGitHub('owner/repository')
    ->prefix('exampleplugin')
    ->register();
```

The prefix names the token constant (`EXAMPLEPLUGIN_GITHUB_TOKEN`) and the options the updater stores, so plugins and themes with different prefixes never share a token or a cache.

## Theme Example

For a theme, the `Update URI` header goes in `style.css`.

```css
/*
Theme Name: Example Theme
Version: 1.2.3
Update URI: https://github.com/owner/repository
*/
```

The updater is registered in `functions.php`.

```php
<?php

use ScottOffen\WpGithubUpdater\Updater;

require_once __DIR__ . '/vendor/autoload.php';

Updater::theme(__FILE__)
    ->fromGitHub('owner/repository')
    ->prefix('exampletheme')
    ->register();
```

## Release Format

The updater reads published GitHub releases and their assets, and never uses the source archive. A repository needs to publish releases in this shape:

- The tag is `v` followed by the version, for example `v1.2.3`.
- A release is a pre-release when its version has a suffix, for example `1.2.3-beta.1`, or when it is marked as a pre-release on GitHub. The updater ignores a release that is either one. It also ignores drafts.
- The release has one asset named `<slug>-<version>.zip`, where the slug is the name of the plugin or theme folder.
- The zip contains a single top-level folder named after the slug.
- The `Version` header inside the zip matches the version in the tag.

The update the updater offers is the highest version among the published releases that are not pre-releases. The release notes on the GitHub release are converted from Markdown to HTML and shown in the update details.

## Private Repositories

A public repository needs no token and no settings page. A private repository needs a token. The recommended token is a fine-grained personal access token with Contents set to read-only and scoped to a single repository.

Define the token as a constant in `wp-config.php`, or save it in the settings section. A constant wins over a saved token.

```php
define('EXAMPLEPLUGIN_GITHUB_TOKEN', 'github_pat_example');
```

The settings section is for managing the token of a private repository. It can be shown on an existing settings page for the plugin or theme, or on a page the updater adds for it.

If the token is revoked or expires, updates stop and WordPress shows no notice. The status line in the settings section shows the access error, but only to someone who opens it.

## Good to Know

- A theme gets updates only while it is active or is the parent of the active theme, because that is the only time WordPress loads the code for the theme.
- A plugin needs a folder of its own. A single-file plugin in the plugins folder can't be updated.
- Multisite isn't supported in this release.
- Each plugin and theme on a site can bundle its own copy of the library. Unless you rewrite the namespace of the library in your own build step, the copies share class names, in which case every copy on a site needs to be the same version.
- The details popup of a plugin always describes the latest release, whichever link opens it. WordPress allows only a narrow set of HTML in that popup, so tables, `<details>`, strikethrough and horizontal rules show as plain text, and the library gives GitHub's alert boxes their colors back with a small stylesheet. A theme's details link opens a page of its own, which shows everything.
- WordPress's own update check never picks up notes you edit on GitHub, because it is answered from the stored result. The Check for updates now button does, and it makes WordPress discard its own update list, which WordPress rebuilds on the next page you open.
- WordPress deactivates a plugin while it updates it, and expects a browser to reactivate it, unless the update runs as a background task. If you run an automatic update from WP-CLI, define `DOING_CRON` first, as in `wp eval "define('DOING_CRON', true); wp_maybe_auto_update();"`, or the plugin stays deactivated.
- Only a plugin can remove the saved token and the stored state of the last check when it is deleted, because WordPress has no uninstall step for themes. A plugin calls `Updater::uninstall('exampleplugin')` from the `uninstall.php` file in the root folder of the plugin, which WordPress runs when the plugin is deleted. If a theme saved a token, remove it on the settings page before you delete the theme.

## Documentation

Full documentation is on the [WordPress GitHub Updater documentation site](https://scottoffen.github.io/wp-github-updater/).

## License

Released under the MIT license.
