# WordPress GitHub Updater

Give your WordPress plugins and themes native updates from public or private GitHub repositories, with integrated update settings and no separate updater plugin.

Updates install the zip that you publish with a release, including its runtime dependencies. The updater selects only stable releases, and it ignores drafts, pre-releases, and the source archive that GitHub generates.

## Quick Start

```bash
composer require scottoffen/wp-github-updater
```

In the following examples, replace `owner/repository` with the repository that the releases are in, and replace the prefix with one of your own. The prefix is used in the name of the token constant, so the constant for the prefix `exampleplugin` is `EXAMPLEPLUGIN_GITHUB_TOKEN`.

### Plugin Example

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

### Theme Example

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

## Settings Integration

Embed the settings section that the updater provides in your existing settings page, or add a separate page for it. The section shows the status of the last check, has a **Check for updates now** button, and manages the token of a private repository.

Public repositories do not require a token, but using one raises the rate limit of the GitHub API. A private repository requires a token.

## Publishing Updates

The updater installs the zip that you attach to a stable GitHub release. Its tag must be a lowercase `v` followed by three numbers, such as `v1.2.3`, and the release must not be a draft or a pre-release.

The release must contain exactly one asset that is a zip with a name that starts with the name of the plugin or theme folder and a hyphen, such as `example-plugin-1.2.3.zip`. The zip must contain a single top-level folder named after the plugin or theme, a `Version` header that matches the tag, and the `vendor` folder with the runtime dependencies.

Using the accompanying packaging script and reusable GitHub Actions workflow from the [scottoffen/workflows](https://github.com/scottoffen/workflows#wordpress-workflows) repository is recommended but not required. They produce a zip in this layout, and any other build process that produces the same zip works too.

## Bundling in Multiple Plugins and Themes

Several plugins and themes on one site can each bundle the library. Rewriting the namespace of the library in your build gives each package its own copy, so the packages can use different versions.

## Requirements and Limitations

The updater requires WordPress 7.1 or later and PHP 8.1 or later, and it has these limits.

- Multisite isn't supported in this release.
- A plugin must be installed in a folder inside the plugins folder. A plugin that is a single file directly in the plugins folder can't be updated.
- A theme gets updates only while it is active or is the parent of the active theme.
- If a token expires or is revoked, updates stop and WordPress shows no notice. The status line in the updater-provided settings section shows the problem.

## Documentation

Full documentation is on the [documentation site](https://scottoffen.github.io/wp-github-updater/).

## License

Released under the MIT license.
