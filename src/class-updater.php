<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater;

use InvalidArgumentException;
use LogicException;
use RuntimeException;
use ScottOffen\WpGithubUpdater\Internal\Github\GitHubClient;
use ScottOffen\WpGithubUpdater\Internal\Package\HeaderReader;
use ScottOffen\WpGithubUpdater\Internal\Package\NotesPage;
use ScottOffen\WpGithubUpdater\Internal\Package\PackageContext;
use ScottOffen\WpGithubUpdater\Internal\Package\PackageDownloader;
use ScottOffen\WpGithubUpdater\Internal\Package\PluginPackage;
use ScottOffen\WpGithubUpdater\Internal\Package\PluginUpdater;
use ScottOffen\WpGithubUpdater\Internal\Package\ReleaseNotes;
use ScottOffen\WpGithubUpdater\Internal\Package\ThemePackage;
use ScottOffen\WpGithubUpdater\Internal\Package\ThemeUpdater;
use ScottOffen\WpGithubUpdater\Internal\Platform\Platform;
use ScottOffen\WpGithubUpdater\Internal\Release\ReleaseResolver;
use ScottOffen\WpGithubUpdater\Internal\Settings\SettingsView;
use ScottOffen\WpGithubUpdater\Internal\Token\TokenStore;
use ScottOffen\WpGithubUpdater\Internal\Update\ReleaseCache;
use ScottOffen\WpGithubUpdater\Internal\Update\UpdateChecker;
use ScottOffen\WpGithubUpdater\Internal\WordPress\WpPlatform;

/**
 * A builder for setting up a plugin or a theme to receive updates from GitHub
 * releases.
 *
 * The following code registers the updater in a plugin and in a theme.
 *
 *     // In the main file of a plugin
 *     Updater::plugin(__FILE__)
 *         ->fromGitHub('owner/repository')
 *         ->prefix('exampleplugin')
 *         ->register();
 *
 *     // In the `functions.php` file of a theme
 *     Updater::theme(__FILE__)
 *         ->fromGitHub('owner/repository')
 *         ->prefix('exampletheme')
 *         ->register();
 *
 * Replace `owner` with the user or organization that owns the repository,
 * replace `repository` with the name of the repository, and replace the prefix
 * with a unique prefix for the plugin or theme.
 *
 * The repository and the prefix are required. The `register()` method throws an
 * exception when the setup isn't valid.
 *
 * A plugin can remove what the updater stored by calling the `uninstall()`
 * method in its `uninstall.php` file.
 *
 *     require __DIR__ . '/vendor/autoload.php';
 *
 *     ScottOffen\WpGithubUpdater\Updater::uninstall('exampleplugin');
 *
 * Replace `exampleplugin` with the prefix that the plugin was set up with.
 */
final class Updater
{
    private const PLUGIN = 'plugin';
    private const THEME = 'theme';

    private string $repository = '';

    private string $prefix = '';

    private string $capability = 'manage_options';

    private ?string $assetPattern = null;

    private ?PackageUpdater $registered = null;

    private function __construct(
        private readonly string $kind,
        private readonly string $file,
        private readonly ?Platform $platform,
    ) {
    }

    /**
     * Sets the pattern for the file name of the release asset.
     *
     * The default is the slug followed by `-*.zip`, where the slug is the name
     * of the folder of the plugin or theme. The updater matches the pattern
     * against the file name of each asset with the `fnmatch()` function, in
     * which `*` matches any run of characters, `?` matches one character, and
     * a set of characters in square brackets, such as `[0-9]`, matches one
     * character from the set. The match is case sensitive.
     *
     * @param string $pattern A file name pattern, such as
     *     `exampleplugin-*.zip`. Replace `exampleplugin` with the name of the
     *     folder of the plugin or theme. The pattern is a name and not a path.
     *
     * @return self The builder.
     *
     * @throws InvalidArgumentException If the pattern is empty or has a slash
     *     or a backslash.
     */
    public function assetPattern(string $pattern): self
    {
        if ($pattern === '' || str_contains($pattern, '/') || str_contains($pattern, '\\')) {
            throw new InvalidArgumentException('The asset pattern must be a file name pattern with no slashes.');
        }

        $this->assetPattern = $pattern;

        return $this;
    }

    /**
     * Sets the capability that the updater-provided settings section requires.
     *
     * The default is `manage_options`.
     *
     * @param string $capability The capability, using letters, digits,
     *     underscores, and hyphens.
     *
     * @return self The builder.
     *
     * @throws InvalidArgumentException If the capability is empty or has any
     *     other character.
     */
    public function capability(string $capability): self
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/D', $capability) !== 1) {
            throw new InvalidArgumentException('The capability must be letters, digits, underscores, and hyphens.');
        }

        $this->capability = $capability;

        return $this;
    }

    /**
     * Sets the repository that the releases are in.
     *
     * The `register()` method checks the repository and throws an exception
     * when it isn't set or isn't written `owner/repository`.
     *
     * @param string $repository The repository, written `owner/repository`.
     *     Replace `owner` with the user or organization that owns the
     *     repository, and `repository` with the name of the repository.
     *
     * @return self The builder.
     */
    public function fromGitHub(string $repository): self
    {
        $this->repository = $repository;

        return $this;
    }

    /**
     * Starts the setup for a plugin.
     *
     * @param string $file The path of the main file of the plugin.
     * @param Platform|null $platform The platform to run on. Omit it to run on
     *     WordPress. The `Platform` type is internal and can change in any
     *     release.
     *
     * @return self The builder.
     */
    public static function plugin(string $file, ?Platform $platform = null): self
    {
        return new self(self::PLUGIN, $file, $platform);
    }

    /**
     * Sets the prefix that the names of the token constant and the options are
     * derived from.
     *
     * Each plugin and theme on a site must have its own prefix. Two updaters
     * with the same prefix share a token, a stored state, and the names of the
     * settings actions. The `register()` method throws an exception when the
     * prefix isn't set or isn't valid.
     *
     * @param string $prefix Lowercase letters, digits, and underscores,
     *     starting with a letter.
     *
     * @return self The builder.
     */
    public function prefix(string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * Registers the update hooks and the handlers for the updater-provided
     * settings section, and returns the updater.
     *
     * A second call on the same builder returns the updater from the first
     * call.
     *
     * @return PackageUpdater The updater for the plugin or theme.
     *
     * @throws LogicException If the `fromGitHub()` method or the `prefix()`
     *     method wasn't called.
     * @throws InvalidArgumentException If the repository isn't written
     *     `owner/repository`, the prefix isn't valid, the file of a plugin
     *     isn't a PHP file, the plugin isn't in a folder inside the plugins
     *     folder, the file of a theme isn't `functions.php`, or the name of the
     *     folder isn't a valid slug.
     * @throws RuntimeException If the setup is for a plugin and the
     *     `WP_PLUGIN_DIR` constant isn't defined as a string.
     */
    public function register(): PackageUpdater
    {
        if ($this->registered !== null) {
            return $this->registered;
        }

        if ($this->repository === '') {
            throw new LogicException('Call fromGitHub() before register().');
        }

        if ($this->prefix === '') {
            throw new LogicException('Call prefix() before register().');
        }

        $platform = $this->platform ?? WpPlatform::create();

        if ($this->kind === self::THEME) {
            $package = ThemePackage::fromFile($this->file);
        } else {
            $pluginDir = $platform->constants->get('WP_PLUGIN_DIR');

            if (!is_string($pluginDir)) {
                throw new RuntimeException('The WP_PLUGIN_DIR constant must be defined as a string.');
            }

            $package = PluginPackage::fromFile($this->file, $pluginDir);
        }

        $tokens = new TokenStore($platform->options, $platform->constants, $this->prefix);

        $checker = new UpdateChecker(
            new GitHubClient($platform->http, $this->repository),
            new ReleaseResolver($this->assetPattern ?? $package->slug . '-*.zip'),
            $tokens,
            new ReleaseCache($platform->options, $platform->clock, $this->prefix),
            $platform->clock,
        );

        $downloader = new PackageDownloader($platform->http, $platform->files, $tokens);
        $notes = new ReleaseNotes($platform->htmlSanitizer, $platform->messages);

        $prefix = $this->prefix;
        $capability = $this->capability;
        $settingsFactory = static fn (PackageContext $updater): Settings => new Settings(
            $updater,
            $platform->admin,
            new SettingsView($platform->settingsText),
            $platform->hooks,
            $prefix,
            $capability,
        );

        $updater = $package instanceof ThemePackage
            ? new ThemeUpdater(
                $package,
                $this->repository,
                $this->capability,
                $this->prefix . '_updater_notes',
                $checker,
                $downloader,
                $tokens,
                $notes,
                $platform->files,
                new HeaderReader(),
                $platform->hooks,
                $platform->messages,
                $platform->updateData,
                $platform->admin,
                new NotesPage(),
                $settingsFactory,
            )
            : new PluginUpdater(
                $package,
                $this->repository,
                $this->capability,
                $checker,
                $downloader,
                $tokens,
                $notes,
                $platform->files,
                new HeaderReader(),
                $platform->hooks,
                $platform->messages,
                $platform->updateData,
                $settingsFactory,
            );

        $updater->addHooks();
        $updater->settings()->addHooks();

        return $this->registered = $updater;
    }

    /**
     * Starts the setup for a theme.
     *
     * @param string $file The path of the `functions.php` file of the theme.
     * @param Platform|null $platform The platform to run on. Omit it to run on
     *     WordPress. The `Platform` type is internal and can change in any
     *     release.
     *
     * @return self The builder.
     */
    public static function theme(string $file, ?Platform $platform = null): self
    {
        return new self(self::THEME, $file, $platform);
    }

    /**
     * Removes the saved token and the stored state for a prefix.
     *
     * No other option is removed. A token constant in `wp-config.php` is not
     * removed, and the saved token is removed even when the token constant is
     * in use.
     * Removing an option that isn't stored does nothing. The method doesn't
     * register any hooks. Call it from the `uninstall.php` file of a plugin.
     *
     * @param string $prefix The prefix that the plugin or theme was set up
     *     with.
     * @param Platform|null $platform The platform to run on. Omit it to run on
     *     WordPress. The `Platform` type is internal and can change in any
     *     release.
     *
     * @throws InvalidArgumentException If the prefix isn't lowercase letters,
     *     digits, and underscores that start with a letter. Nothing is removed.
     */
    public static function uninstall(string $prefix, ?Platform $platform = null): void
    {
        $platform ??= WpPlatform::create();

        $tokens = new TokenStore($platform->options, $platform->constants, $prefix);
        $cache = new ReleaseCache($platform->options, $platform->clock, $prefix);

        $platform->options->delete($tokens->optionName());
        $cache->clear();
    }
}
