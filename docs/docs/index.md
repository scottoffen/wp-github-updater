---
sidebar_position: 1
---

# Getting Started

This page covers installing WordPress GitHub Updater, setting up a plugin or a theme to update from the releases of a GitHub repository, and checking that the first update works.

## Install

```bash
composer require scottoffen/wp-github-updater
```

Because the plugin or theme loads the Composer autoloader, the release zip must include the `vendor` folder. See [Packaging and Releases](./packaging-and-releases.md) for how to build that zip.

## Set Up a Plugin

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

The updater doesn't add a page or a menu item to the admin. To see the status of the last check or to force one, add the [settings section that the updater provides](./settings-section.md), which works for public and private repositories alike.

## Set Up a Theme

Add an `Update URI` header to the `style.css` file of the theme.

```css
/*
Theme Name: Example Theme
Version: 1.2.3
Update URI: https://github.com/owner/repository
*/
```

Then register the updater in the `functions.php` file of the theme.

```php
<?php

use ScottOffen\WpGithubUpdater\Updater;

require_once __DIR__ . '/vendor/autoload.php';

Updater::theme(__FILE__)
    ->fromGitHub('owner/repository')
    ->prefix('exampletheme')
    ->register();
```

The `register()` method throws an exception for any file other than the `functions.php` file of the theme.

The updater doesn't add a page or a menu item to the admin. To see the status of the last check or to force one, add the [updater-provided settings section](./settings-section.md), which works for public and private repositories alike.

## Setup Method Details

The following table describes what each part of the setup does. The code for a plugin and for a theme differs only in the first call after `Updater::`, which is `plugin()` for a plugin and `theme()` for a theme, so the table applies to both.

| Part | Description |
| --- | --- |
| `Updater::plugin(__FILE__)` and `Updater::theme(__FILE__)` | Starts the setup for a plugin or for a theme. Both calls take the path of the file that they are called from, which should be `__FILE__`. For a plugin, that is the main file of the plugin. For a theme, that is the `functions.php` file of the theme. The name of the folder that contains the file is the slug, which can use letters, digits, dots, hyphens, and underscores. The `register()` method throws an exception when the file of a plugin isn't a PHP file, when the file of a theme isn't `functions.php`, or when the slug isn't valid. |
| `Update URI` header | The host of the address is `github.com`, so WordPress calls the updater when it checks the plugin or theme for updates. WordPress reads only the host, and the updater doesn't read the header at all, so the rest of the address has no effect. The header and `fromGitHub()` should refer to the same repository. |
| `fromGitHub('owner/repository')` | Sets the repository that the releases are read from. Replace `owner` with the name of the user or organization that owns the repository, and `repository` with the name of the repository. |
| `prefix()` | Sets the names of the token constant and of the options that the updater stores. Replace the prefix in the example with a prefix of your own, using lowercase letters, digits, and underscores, and starting with a letter. For the `exampleplugin` prefix, the constant is `EXAMPLEPLUGIN_GITHUB_TOKEN`. A plugin and a theme with the same prefix share a token and a cache, so give every plugin and theme on a site its own prefix. |
| `register()` | Registers the update hooks and returns the updater, from which additional methods can be called to set up the [updater-provided settings section](./settings-section.md). The `register()` method is idempotent. Calling it more than once on the same builder returns the same updater. An exception is thrown when either the repository or the prefix is missing or invalid, both of which will cause failures when the plugin or theme loads. |

The builder has two optional methods, `capability()` and `assetPattern()`. They are described in the [Reference](./reference.md).

## Check That It Works

To confirm the setup, publish a release and let WordPress find it.

1. Install the plugin or theme on a site, using a version number that is lower than the release that you plan to publish.
2. Publish a stable release in the repository, following [Packaging and Releases](./packaging-and-releases.md).
3. In the WordPress admin, open Dashboard, then Updates.
4. Confirm that the plugin or theme is listed with the new version, then update it.

:::note
The updater reuses the result of a successful check for 12 hours, so a release published after the last check can take a while to appear. The **Check for updates now** button in the [updater-provided settings section](./settings-section.md) sends a request to GitHub right away and makes WordPress rebuild its update list. A private repository will require a [token](./private-repositories.md) before the first check can succeed.
:::

## Requirements and Limitations

The updater requires WordPress 7.1 or later and PHP 8.1 or later, and it has these limits.

- Multisite isn't supported in this release.
- A plugin must be installed in a folder inside the plugins folder. A plugin that is a single file directly in the plugins folder can't be updated.
- WordPress loads the `functions.php` file of a theme only while the theme is active or is the parent of the active theme, so a theme gets updates only then.
