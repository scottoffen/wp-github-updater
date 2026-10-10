---
sidebar_position: 2
---

# Packaging and Releases

This page covers how to publish a GitHub release that the updater will recognize, what the zip must contain, how the release notes are shown, and what to do when more than one plugin or theme on a site bundles the library.

## Release Rules

The updater reads the published releases of the repository and their assets. GitHub generates a source code archive for every release, but the updater doesn't use it. The updater installs only the zip archive that you upload to the release as an asset, described under [Release Asset](#release-asset). For more about releases and tags, see the GitHub documentation on [releases](https://docs.github.com/en/repositories/releasing-projects-on-github/about-releases) and [tags](https://docs.github.com/en/repositories/releasing-projects-on-github/viewing-your-repositorys-releases-and-tags).

The updater can select a release only when all of the following are true.

- The release isn't a draft.
- The release isn't marked as a pre-release on GitHub.
- The tag of the release is a lowercase `v` followed by three numbers separated by dots, such as `v1.2.3`, with no leading zeros in a number.

The updater selects only stable releases. Under [Semantic Versioning](https://semver.org/#spec-item-9), a version with a suffix after the three numbers is a pre-release. A release with a tag such as `v1.2.3-beta.1` therefore won't be selected, even when the release isn't a draft and isn't marked as a pre-release on GitHub.

The updater retrieves the 30 most recent releases for the repository from GitHub. Of those, it selects the release with the highest version that meets all of these conditions, compared as version numbers, so `v1.10.0` is higher than `v1.9.0`. The date of a release and its position in the list don't matter.

## Release Asset

The release must contain exactly one asset that is a zip archive with a name that starts with the slug and a hyphen, and ends in `.zip`. The slug is the name of the plugin or theme folder, so a plugin in the `example-plugin` folder uses an asset such as `example-plugin-1.2.3.zip`. The name is case sensitive, and the updater doesn't read the version from it.

Other assets, such as a checksum file, are ignored. A second zip archive whose name also starts with the slug and a hyphen, such as `example-plugin-docs.zip`, makes the release ambiguous. The updater doesn't know how to choose between the two, so the release can't be installed.

When the release with the highest version has no asset with a matching name, or has more than one, the updater doesn't fall back to an older release, so WordPress shows no update. The [updater-provided settings section](./settings-section.md), which shows the status of the last check, will display the message "The newest release is not set up correctly, so it cannot be installed." Add the missing asset or remove the extra one, then click **Check for updates now**, or wait an hour for the next check. See [Troubleshooting](./troubleshooting.md) for the meaning of other messages.

### Custom Asset Names

If the assets of your releases are named another way, tell the updater which name to look for with `assetPattern()`. The default is `<slug>-*.zip`. In the value, `*` stands for any run of characters and `?` stands for one character. For example, a plugin in the `example-plugin` folder that publishes `exampleplugin-1.2.3.zip` uses this setup.

```php
Updater::plugin(__FILE__)
    ->fromGitHub('owner/repository')
    ->prefix('exampleplugin')
    ->assetPattern('exampleplugin-*.zip')
    ->register();
```

The value is a file name pattern, so it can't be empty or contain a slash. See the [Reference](./reference.md) for the full signature.

## Zip Contents

The zip contains a single top-level folder, and the folder is named after the slug. WordPress installs an update into the folder named inside the zip, and the updater rejects an unpacked update whose folder has any other name. Because the plugin or theme loads the Composer autoloader, the folder must contain the files of the plugin or theme and the `vendor` folder with the runtime dependencies.

```text
example-plugin-1.2.3.zip
  example-plugin/
    example-plugin.php
    vendor/
```

The `Version` header (either in the main file of the plugin or in the `style.css` file of the theme) must match the version in the tag. The updater doesn't compare them, but WordPress compares the `Version` header of the installed plugin or theme with the version of the tag, so a zip whose `Version` header is `1.2.2` for the `v1.2.3` tag installs, causing WordPress to then show the same update again.

## Building the Zip

The updater doesn't require the plugin or theme to use a specific build tool. Any process that produces the zip described above will work. One option is the packaging script and the reusable GitHub Actions workflow in the [scottoffen/workflows](https://github.com/scottoffen/workflows#wordpress-workflows) repository. They are two separate pieces that work together: the script builds the zips, and the workflow runs the script in CI.

The script packages each plugin and theme under `src/`, runs `composer install --no-dev` for a package that has a `composer.json`, and creates each zip with the name `<folder>-<version>.zip`, which is the name that the updater looks for by default. Each repository contains its own copy of the script, in `scripts/wordpress-package.ps1`, and the same copy runs locally and in CI.

The workflow contains no packaging logic, so it works only when the script is in your repository, at `scripts/wordpress-package.ps1` by default. When a run on the default branch packages a single plugin or theme and there is no existing tag for the version, the workflow will also create a draft release with the zip attached. The updater ignores drafts, so it can select the release only after you publish it. The [WordPress Package](https://github.com/scottoffen/workflows#wordpress-package) section of the repository describes how to set up the workflow.

:::note
The workflow generates release notes for the draft as a starting point. Release notes will appear in the update details, so be sure to update them before you publish the release.
:::

## Release Notes

The notes on the GitHub release are written in Markdown and converted to HTML when they are shown. A plugin shows them in the details popup, and a theme shows them on a separate page. A release with no notes shows a short message saying that none were published.

The popup of a plugin is built from the `Plugin Name`, `Author`, and `Plugin URI` headers in the main file, the date that the release was published, and the notes in a Changelog section. It always describes the latest available release, regardless of the text on the link used to open it.

Images in the notes load only from `github.com` and `githubusercontent.com` hosts. Any other image becomes a link.

WordPress allows only a narrow set of HTML in the details popup of a plugin. Tables, `<details>` blocks, strikethrough, and horizontal rules show as plain text there. WordPress also removes the colors of GitHub's alert boxes (`NOTE`, `TIP`, `IMPORTANT`, `WARNING`, and `CAUTION`), so the updater adds a small stylesheet that restores them. It prints the stylesheet only on the popup of that plugin. The details link of a theme update opens a separate page that isn't limited to that set, so tables, strikethrough, horizontal rules, and the colors of alert boxes appear there.

The popup and the theme page show the notes stored by the last check and never send a request to GitHub. A change to the notes on GitHub won't appear until after the next check. That is when the stored result is more than 12 hours old and WordPress checks for updates, or when someone clicks **Check for updates now** in the [updater-provided settings section](./settings-section.md).

## Bundling in Multiple Plugins and Themes

Each plugin or theme that uses the updater includes its own copy in its `vendor` folder, and every copy uses the same class names. PHP declares a class once per request, so one copy of each class serves every plugin and theme on the site, and that copy is determined by the order in which they load. Unless you rewrite the namespace of the library in your own build step, keep every copy on a site at the same version.

A build step that rewrites the namespace gives a package its own copy of the library. That package can then use any version, independent of the others.
