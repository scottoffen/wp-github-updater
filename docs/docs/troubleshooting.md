---
sidebar_position: 6
---

# Troubleshooting

This page covers the messages that the updater shows, what causes each one, and what to do about it. It also lists a few problems that have no message.

:::note
The messages use "update server" where this page uses GitHub, and "access token" where the other pages use token.
:::

## Status Line

The following table describes the messages that the status line in the [updater-provided settings section](./settings-section.md) shows for a check that did not succeed. After a rate limit, a network failure, or a response that can't be used, the line may still show the version of a release that an earlier check found.

| Message | Cause | Fix |
| --- | --- | --- |
| Access was refused. Check the access token. | The GitHub API responded with 401 Unauthorized, or with 403 Forbidden when the rate limit was not used up. | Configure a valid token. See [Creating a Token](./private-repositories.md#creating-a-token). |
| The updates could not be found. If they are private, check the access token. | The GitHub API responded with 404 Not Found. The repository name in `fromGitHub()` is wrong, or the repository is private and the token is missing or has no access to it. | Check the repository name, and configure a token if the repository is private. See [Token Requirements](./private-repositories.md#token-requirements). |
| The update server is limiting requests right now. | The GitHub API responded with 429 Too Many Requests, or with 403 Forbidden when the rate limit was used up. | Wait for the limit to reset, or configure a token to raise it. See [Token Requirements](./private-repositories.md#token-requirements). |
| The update server could not be reached. | The request to GitHub failed before GitHub responded. | Check that the server can send requests to `api.github.com`, then click **Check for updates now**. |
| The update server sent a response that could not be used. | The GitHub API responded with an error such as a server error, or with a body that isn't a list of releases. | Click **Check for updates now** again later. |
| No release found. | The repository has no release that the updater can select. | Publish a release that follows [Release Rules](./packaging-and-releases.md#release-rules). |
| The newest release is not set up correctly, so it cannot be installed. | The newest release does not have exactly one asset with a matching name. | See [Release Asset](./packaging-and-releases.md#release-asset). |
| The last check did not succeed. | The check ended in a result that has no specific message. | Click **Check for updates now**. |

## Update Errors

The following table describes the messages that WordPress shows when an update fails to download or install.

| Message | Cause | Fix |
| --- | --- | --- |
| The update could not be downloaded because access was refused. Check the access token in the settings for this plugin or theme. | The GitHub API responded to the download request with 401 Unauthorized, or with 403 Forbidden when the rate limit was not used up. | Configure a valid token. See [Creating a Token](./private-repositories.md#creating-a-token). |
| The update could not be found. If the update source is private, check the access token in the settings for this plugin or theme. | The GitHub API responded to the download request with 404 Not Found. The release asset no longer exists, or the repository is private and the token is missing or has no access to it. | Click **Check for updates now**, and configure a token if the repository is private. |
| The update server is limiting requests right now. Try again in a little while. | The GitHub API responded to the download request with 429 Too Many Requests, or with 403 Forbidden when the rate limit was used up. | Wait for the limit to reset, or configure a token to raise it. |
| The update server could not be reached. Try again in a little while. | The download request failed before GitHub responded. | Check that the server can send requests to `api.github.com`, then try the update again. |
| The update server sent a response that could not be used. Try again in a little while. | GitHub or the storage host responded with an error, such as an expired signed address, or a redirect had no usable address or went past five redirects. | Try the update again. |
| The downloaded update did not match its checksum, so it was not installed. Try again. | The SHA-256 digest of the downloaded file differs from the digest that GitHub reported for the asset. | Click **Check for updates now**, then try the update again. A mismatch that repeats means that the downloaded file is not the file that GitHub reports for the asset. |
| The update could not be saved to a temporary file. Check that the temporary directory on the server can be written to. | The updater could not create or read the temporary file for the download. | Make the temporary directory of the server writable. |
| The address of the update was not accepted, so nothing was downloaded. | The address of the release asset is not an address on `api.github.com` that points at a release asset. | Click **Check for updates now**, then try the update again. |
| The update package is not in the right folder, so it was not installed. | The zip does not contain a single top-level folder that is named after the slug. | See [Zip Contents](./packaging-and-releases.md#zip-contents). |
| No release information is available yet. | WordPress requested a download or the details of an update, and the updater has no stored release. No check has run yet, or the last check removed the release. | Read the status line, then click **Check for updates now**. |
| The update could not be completed. | The update failed for a reason that has no specific message. | Try the update again. |

## Notices

The following table describes the notices that appear after a button in the [updater-provided settings section](./settings-section.md) is used.

| Message | When it appears | What to do |
| --- | --- | --- |
| The access token was saved and the updates were checked. | After **Save token** saves a token. | Read the status line for the result of the check. |
| The access token was removed and the updates were checked. | After **Remove token** removes a token. | Read the status line for the result of the check. |
| The updates were checked. | After **Check for updates now**. | Read the status line for the result of the check. |
| Enter an access token to save it. | **Save token** was clicked with an empty field. | Enter a token. |
| That access token cannot be used. Check that it was copied exactly, with no spaces. | The token has spaces inside it, or characters that are not printable ASCII. | Copy the token again, exactly as GitHub shows it. |
| The access token is set in the site configuration and cannot be changed here. | The token comes from a constant, so the section can't save or remove a token. | Change the constant in `wp-config.php`. See [Configuring the Token](./private-repositories.md#configuring-the-token). |
| The form expired. Please try again. | The security check of the form failed, which happens when the page has been open for a long time. | Reload the page and click the button again. |

## Other Problems

The following problems have no message.

### An Update Does Not Appear

The status line shows a message that describes the problem when a check did not succeed, so read it first. If it shows no problem, check the following.

- The stored result of the last check is still in use. Click **Check for updates now**. See [Update Checks](./how-updates-work.md#update-checks).
- The release does not follow the rules for a stable release. See [Release Rules](./packaging-and-releases.md#release-rules).
- The version of the release is not higher than the installed version. WordPress shows an update only when it is higher.
- The `Update URI` header is not an address on `github.com`. See [Set Up a Plugin](./index.md#set-up-a-plugin).
- The plugin is not in a folder inside the plugins folder, or the theme is not active or the parent of the active theme. See [Requirements and Limitations](./index.md#requirements-and-limitations).

### The Same Update Appears Again After It Installs

The `Version` header in the zip does not match the version in the tag, so WordPress still sees the old version. See [Zip Contents](./packaging-and-releases.md#zip-contents).

### The Update Details Show No Release Notes

The details popup of a plugin and the details page of a theme show "No release notes were published for this version." when the release on GitHub has no notes. Add notes to the release, then click **Check for updates now**. See [Release Notes](./packaging-and-releases.md#release-notes).
