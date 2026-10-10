---
sidebar_position: 5
---

# How Updates Work

This page covers when the updater checks GitHub for a release, what the updater stores after a check, how an update is downloaded and installed, how plugins and themes differ, and how the updater works alongside other plugins and themes on the same site.

## Update Checks

WordPress decides when to check for updates, and the updater doesn't have a schedule. During an update check, WordPress runs a filter for each plugin or theme whose `Update URI` is on `github.com`, and the updater returns the latest release from that filter.

When the filter runs, if the last check was within the past 12 hours and succeeded, or within the past hour and did not succeed, then the updater returns the stored result without a request to GitHub.

When the stored result is out of date, the updater sends one request to GitHub for the releases of the repository. If a release is stored, the request sends the ETag of that release in an `If-None-Match` header. A 304 Not Modified response means that the stored release is still the newest, so the updater doesn't change it and records the time of the check. GitHub doesn't count a 304 response against the primary rate limit when the request sends a token. See the GitHub documentation on [conditional requests](https://docs.github.com/en/rest/using-the-rest-api/best-practices-for-using-the-rest-api) for the details.

The updater also checks when someone uses the [updater-provided settings section](./settings-section.md). The **Check for updates now** button skips the stored result and sends no ETag, so the response is always a full one, and saving or removing a token does the same.

## Stored Results

The following table describes what the updater stores for each result of a check.

| Result of the check | Stored release and ETag |
| --- | --- |
| A release was found | The release and its ETag are stored. |
| 304 Not Modified | The stored release and ETag are unchanged, and the time of the check is updated. |
| A network failure, a rate limit (429 Too Many Requests, or 403 Forbidden when the rate limit is used up), or a response that the updater can't use, such as a server error or a body that isn't a list of releases | The stored release and ETag are unchanged. |
| 401 Unauthorized, 403 Forbidden when the rate limit isn't used up, 404 Not Found, no stable release, or a newest release without exactly one matching asset | The stored release and ETag are removed. |

A network failure, a rate limit, or an unusable response provides no information about the release, so the updater doesn't change the stored release, and an outage doesn't hide an update that was already found. The other results are clear responses, and the updater has no release that it can use after any of them, so it removes the stored release. The updater never falls back to an older release, so when the newest release is not set up correctly, no update is available until the release is fixed.

## Update Downloads

WordPress downloads the package when someone updates the plugin or theme, and the updater handles that download. The package address that WordPress has stored can be older than the stored state, so the updater downloads the release in the stored state instead.

The download has the following protections.

- The updater requests only an address on `api.github.com` that points at a release asset, and it refuses any other address for the asset.
- The updater follows redirects itself and checks each address before it requests it. The GitHub API can respond to a request for a release asset with a redirect to another asset address on `api.github.com`, for example when the repository has been renamed or transferred, and then with a redirect to a storage host. The token goes only to addresses on `api.github.com` that point at a release asset, so the request to the storage host has none. The updater stops the download after five redirects.
- When GitHub reports a SHA-256 digest for the asset, the downloaded file must match it, or the install stops. A digest in another algorithm isn't checked.
- The updater deletes the temporary file when the download does not succeed.

WordPress installs the update into the folder named inside the package, so the updater checks that the unpacked update is in a folder named after the slug and stops the install when it isn't. See [Troubleshooting](./troubleshooting.md) for the message that each failure shows.

## Plugins and Themes

The updater checks for releases and downloads them in the same way for plugins and themes. The differences are in the WordPress hooks that each one registers, in how the release notes are shown, and in how the stored state is removed.

### Plugins

WordPress runs the `update_plugins_github.com` filter for a plugin whose `Update URI` is on `github.com`, and the updater returns the version and package of the stored release from it. The details popup of the plugin shows the notes of the stored release. See [Release Notes](./packaging-and-releases.md#release-notes) for how the notes are shown.

A plugin removes its saved token and the stored state of its last check when it is deleted, by calling `Updater::uninstall()`. See [Plugins](./private-repositories.md#plugins) for the call.

### Themes

WordPress runs the `update_themes_github.com` filter for a theme whose `Update URI` is on `github.com`. The details link of a theme update opens a separate page that shows the notes of the stored release, and only someone with the `update_themes` capability can open it.

The updater is registered from the `functions.php` file of the theme, so a theme gets updates only while it is active or is the parent of the active theme. See [Requirements and Limitations](./index.md#requirements-and-limitations).

Nothing uses the stored state while a theme is inactive, so the updater removes it when the theme is deactivated, and the first check after the theme is active will rebuild it. A saved token can't be rebuilt by a check, so the updater doesn't remove it, and the theme uses it again when the theme is activated again. A token that is no longer needed stays in the options table until someone removes it in the [updater-provided settings section](./settings-section.md). See [Removing the Token on Uninstall](./private-repositories.md#removing-the-token-on-uninstall) for what to do before you delete a theme.

## Other Plugins and Themes

Other plugins and themes on the same site can have an `Update URI` on `github.com` too, and none of them prevents another from updating. WordPress runs the same filter for every one of them, and each updater acts only on the plugin or theme that it is registered for.

For any other plugin or theme, the updater returns the value that WordPress passed in, unchanged, and sends no request to GitHub. It handles a download or an unpacked update only for the plugin or theme that it is registered for, and it fills the details popup only for that plugin. The updater doesn't change the update information that a different updater returns for another plugin or theme.

The release, the token, and the stored state belong to the prefix, so two updaters with different prefixes won't share them. The same is true when both read releases from the same repository, and a plugin and a theme can have the same slug.

This depends on two requirements.

- Each plugin and theme on a site must have its own prefix. Two updaters with the same prefix share a token, a stored state, and the names of the settings actions.
- Several copies of the library on one site must be the same version, or each copy must have its namespace rewritten. See [Bundling in Multiple Plugins and Themes](./packaging-and-releases.md#bundling-in-multiple-plugins-and-themes).
