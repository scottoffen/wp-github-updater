---
sidebar_position: 3
---

# Private Repositories

This page covers how to create a GitHub token for a private repository and configure it as a constant or in the updater-provided settings section, how the token is stored, how the updater behaves when the token is missing or invalid and what to do about it, and how to remove the token on uninstall.

## Token Requirements

Public repositories do not require a token, but using a token can still be useful. The GitHub API limits requests without a token to 60 requests per hour for each IP address, so sites that share an IP address share that limit. Requests with a token are limited to 5,000 requests per hour. See the GitHub documentation on [rate limits for the REST API](https://docs.github.com/en/rest/using-the-rest-api/rate-limits-for-the-rest-api).

The GitHub API responds with 404 Not Found to a request for a private repository without a token, so a private repository requires one.

The [updater-provided settings section](./settings-section.md) isn't only for tokens. It also has a **Check for updates now** button and a status line that shows the result of the last check, so it is useful for a public repository too.

## Creating a Token

GitHub recommends [fine-grained personal access tokens](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens) over classic ones. For the updater, create a fine-grained token with access to only the repository that the releases are in, and set the Contents permission to read-only. Reading releases and downloading their assets requires read access to Contents. A token with this scope limits what someone can read if the token leaks.

A fine-grained token has an expiration date that you choose when you create it. When the token expires, updates stop. See [Missing or Invalid Tokens](#missing-or-invalid-tokens).

## Configuring the Token

The updater reads the token from one of two places: a constant or a saved token.

Define the constant in `wp-config.php`, above the line that tells you to stop editing, with your token as the value. The name of the constant is the prefix of the plugin or theme in uppercase, followed by `_GITHUB_TOKEN`. For the `exampleplugin` prefix, the constant is `EXAMPLEPLUGIN_GITHUB_TOKEN`.

```php
define('EXAMPLEPLUGIN_GITHUB_TOKEN', 'github_pat_example');
```

A constant is only valid when its value is a non-empty string. An empty constant is ignored, and the updater falls back to looking for a saved token.

Tokens can be saved using the [updater-provided settings section](./settings-section.md). The field in the section accepts a token and shows a saved token as a masked placeholder.

A constant takes precedence over a saved token. When the token comes from a constant, the updater-provided settings section shows the name of the constant in a locked field and can't save or remove a token.

## Token Storage

A saved token is stored in the options table, in an option named from the prefix, such as `exampleplugin_updater_github_token`. The token is stored as it is. It isn't encrypted, so anyone who can read or export the database can read the token. The option doesn't autoload, so WordPress doesn't load it on every request. A token in a constant in `wp-config.php` isn't in the database.

The updater sends the token only to `api.github.com`. For the download of a release asset, the GitHub API responds with a redirect to a storage host, and the updater sends the request to that host without the token. See [How Updates Work](./how-updates-work.md).

## Missing or Invalid Tokens

When the token is invalid because it expired or was revoked, the GitHub API responds to the requests of the updater with 401 Unauthorized, and updates stop. When the token is missing or has no access to the repository, the API responds with 404 Not Found, and updates stop as well. WordPress shows no notice. The status line in the updater-provided settings section shows a message about the access error, but only to someone who opens the section. See [Troubleshooting](./troubleshooting.md) for the messages.

To fix it, configure a valid token. If the token comes from a constant, set the value of the constant. If the token is saved, save the new one in the updater-provided settings section, which checks for updates right away.

## Removing the Token on Uninstall

WordPress has no uninstall step for themes, so a theme can't remove its saved token when the theme is deleted. If a theme saved a token, remove it in the updater-provided settings section before you delete the theme. The updater removes the stored state of the last check when the theme is deactivated.

### Plugins

WordPress runs the `uninstall.php` file in the root folder of a plugin when the plugin is deleted. A plugin removes its saved token and the stored state of its last check by calling `Updater::uninstall()` there, with the prefix that it was set up with.

```php
<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require __DIR__ . '/vendor/autoload.php';

ScottOffen\WpGithubUpdater\Updater::uninstall('exampleplugin');
```

WordPress defines `WP_UNINSTALL_PLUGIN` only while it runs the file during an uninstall, so the check at the top stops a direct request to the file from running the call.

The call removes the saved token even when a constant is defined. The constant belongs to the site owner, so the call leaves it alone. It touches nothing else. See the [Reference](./reference.md) for the full signature.
