---
sidebar_position: 7
---

# Reference

This page covers the public API: the `Updater` builder, the `PackageUpdater` returned by the `register()` method, and the updater-provided settings section. It also covers the names that the updater derives from the consumer-provided prefix. The three types are in the `ScottOffen\WpGithubUpdater` namespace.

## Summary

The following table lists the members that this page describes.

| Member | Description |
| --- | --- |
| [`Updater::plugin()`](#updaterplugin) | Starts the setup for a plugin. |
| [`Updater::theme()`](#updatertheme) | Starts the setup for a theme. |
| [`Updater::uninstall()`](#updateruninstall) | Removes the saved token and the stored state of the last check for a prefix. |
| [`fromGitHub()`](#fromgithub) | Sets the repository that the releases are in. |
| [`prefix()`](#prefix) | Sets the prefix that the names of the token constant and the options are derived from. |
| [`capability()`](#capability) | Sets the capability that the settings section requires. |
| [`assetPattern()`](#assetpattern) | Sets the pattern for the file name of the release asset. |
| [`register()`](#register) | Registers the update hooks and returns the updater. |
| [`settings()`](#settings) | Returns the updater-provided settings section. |
| [`capability()` getter](#capability-getter) | Returns the capability that the settings section requires. |
| [`render()`](#render) | Prints the settings section on a page. |
| [`addPage()`](#addpage) | Adds a separate page for the settings section to the admin menu. |

## Entry Points

### `Updater::plugin()`

```php
public static function plugin(string $file): Updater
```

Starts the setup for a plugin and returns the builder so that additional calls can be chained.

| Parameter | Type | Description |
| --- | --- | --- |
| `$file` | `string` | The main file of the plugin, which is `__FILE__` in that file. |

### `Updater::theme()`

```php
public static function theme(string $file): Updater
```

Starts the setup for a theme and returns the builder so that additional calls can be chained.

| Parameter | Type | Description |
| --- | --- | --- |
| `$file` | `string` | The `functions.php` file of the theme, which is `__FILE__` in that file. |

### `Updater::uninstall()`

```php
public static function uninstall(string $prefix): void
```

Removes the saved token and the stored state of the last check for a prefix. Nothing else is removed. A token constant in `wp-config.php` is not removed, and the saved token is removed even when a constant is defined. A plugin calls this method from its `uninstall.php` file. See [Removing the Token on Uninstall](./private-repositories.md#removing-the-token-on-uninstall).

| Parameter | Type | Description |
| --- | --- | --- |
| `$prefix` | `string` | The prefix that the plugin or theme was set up with. |

| Exception | Thrown when |
| --- | --- |
| `InvalidArgumentException` | The prefix is not lowercase letters, digits, and underscores that start with a letter. Nothing is removed. |

## Builder Methods

Each builder method returns the builder so that additional calls can be chained.

### `fromGitHub()`

```php
public function fromGitHub(string $repository): Updater
```

Sets the repository that the releases are in. The repository is required, and `register()` throws an exception when it was not set.

| Parameter | Type | Description |
| --- | --- | --- |
| `$repository` | `string` | The repository, written `owner/repository`. Replace `owner` with the user or organization that owns the repository, and `repository` with the name of the repository. |

### `prefix()`

```php
public function prefix(string $prefix): Updater
```

Sets the prefix that the names of the token constant and the options are derived from. The prefix is required, and `register()` throws an exception when it was not set. See [Names From the Prefix](#names-from-the-prefix).

| Parameter | Type | Description |
| --- | --- | --- |
| `$prefix` | `string` | A prefix of lowercase letters, digits, and underscores, starting with a letter. Each plugin and theme on a site must have its own prefix. |

### `capability()`

```php
public function capability(string $capability): Updater
```

Sets the capability that the settings section requires. The default is `manage_options`. See [Capability](./settings-section.md#capability).

| Parameter | Type | Description |
| --- | --- | --- |
| `$capability` | `string` | The capability, using letters, digits, underscores, and hyphens. |

| Exception | Thrown when |
| --- | --- |
| `InvalidArgumentException` | The capability is empty or has any other character. |

### `assetPattern()`

```php
public function assetPattern(string $pattern): Updater
```

Sets the pattern for the file name of the release asset. The default is `<slug>-*.zip`, where the slug is the name of the plugin or theme folder. See [Custom Asset Names](./packaging-and-releases.md#custom-asset-names).

| Parameter | Type | Description |
| --- | --- | --- |
| `$pattern` | `string` | A file name pattern, such as `exampleplugin-*.zip`. In the pattern, `*` stands for any run of characters and `?` stands for one character. |

| Exception | Thrown when |
| --- | --- |
| `InvalidArgumentException` | The pattern is empty or has a slash. |

### `register()`

```php
public function register(): PackageUpdater
```

Registers the update hooks and returns the `PackageUpdater`. A second call on the same builder returns the updater from the first call.

| Exception | Thrown when |
| --- | --- |
| `LogicException` | `fromGitHub()` or `prefix()` was not called. |
| `InvalidArgumentException` | The repository is not written `owner/repository`, the prefix is not valid, the file of a plugin is not a PHP file, the plugin is not in a folder inside the plugins folder, the file of a theme is not `functions.php`, or the name of the folder is not a valid slug. |

## Settings Section

### `settings()`

```php
public function settings(): Settings
```

Returns the updater-provided settings section. Call it on the object that `register()` returns. See [Updater-Provided Settings Section](./settings-section.md).

### `capability()` Getter

```php
public function capability(): string
```

Returns the capability that the settings section requires, which is `manage_options` unless the [`capability()`](#capability) builder method set another one. Call it on the object that `register()` returns. Use it with `current_user_can()` to check the same capability on your own page.

### `render()`

```php
public function render(string $heading = ''): void
```

Prints the settings section at the place where it is called. It prints nothing for someone who does not have the capability, and it must be called outside any `<form>` element. See [Show the Section on an Existing Page](./settings-section.md#show-the-section-on-an-existing-page).

| Parameter | Type | Description |
| --- | --- | --- |
| `$heading` | `string` | A heading that appears above the section. The default is no heading. |

### `addPage()`

```php
public function addPage(string $title, string $parent): void
```

Adds a separate page for the settings section to the admin menu. The updater adds the page when WordPress builds the admin menu, so call the method when the plugin or theme loads. See [Add a Separate Page](./settings-section.md#add-a-separate-page).

| Parameter | Type | Description |
| --- | --- | --- |
| `$title` | `string` | The title of the page, which is also the label of its menu entry. |
| `$parent` | `string` | The slug of the admin menu to add the page under. The slug `options-general.php` adds the page under Settings, and `themes.php` adds it under Appearance. |

| Exception | Thrown when |
| --- | --- |
| `InvalidArgumentException` | The title or the parent is empty. |

## Names From the Prefix

The following table describes the names that the updater derives from the prefix. In the table, `<prefix>` is the prefix in lowercase and `<PREFIX>` is the prefix in uppercase. The last column shows the names for the prefix `exampleplugin`.

| Name | Description | For `exampleplugin` |
| --- | --- | --- |
| `<PREFIX>_GITHUB_TOKEN` | The constant where the token can be defined. See [Configuring the Token](./private-repositories.md#configuring-the-token). | `EXAMPLEPLUGIN_GITHUB_TOKEN` |
| `<prefix>_updater_github_token` | The option where the saved token is stored. | `exampleplugin_updater_github_token` |
| `<prefix>_updater_release_state` | The option where the state of the last check is stored. | `exampleplugin_updater_release_state` |
| `<prefix>_updater_settings` | The slug of the page that `addPage()` adds. | `exampleplugin_updater_settings` |
