<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater;

use InvalidArgumentException;
use LogicException;
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
 * Sets up a plugin or a theme to receive updates from GitHub releases.
 *
 *     // In the main file of a plugin
 *     Updater::plugin(__FILE__)
 *         ->fromGitHub('owner/repository')
 *         ->prefix('fdcrm')
 *         ->register();
 *
 *     // In the `functions.php` file of a theme
 *     Updater::theme(__FILE__)
 *         ->fromGitHub('owner/repository')
 *         ->prefix('mytheme')
 *         ->register();
 *
 * The repository and the prefix are required. The prefix determines the names
 * of the token constant and the options, so two plugins with different prefixes
 * don't share a token or a cache. The capability defaults to `manage_options`,
 * and the asset pattern to the slug followed by `-*.zip`.
 *
 * When a plugin is deleted, its `uninstall.php` can remove what the updater
 * stored:
 *
 *     require __DIR__ . '/vendor/autoload.php';
 *
 *     ScottOffen\WpGithubUpdater\Updater::uninstall('fdcrm');
 *
 * `register` throws an exception when the setup is wrong. That happens on the
 * first page load of the plugin, so a mistake shows up while the plugin is
 * being built. A second call to `register` on the same builder returns the
 * updater from the first call.
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
     * Starts the setup for a plugin.
     *
     * @param string $file The main file of the plugin, which is `__FILE__`
     *     there.
     * @param Platform|null $platform The platform to run on. Tests pass a fake
     *     platform, and a WordPress site leaves it out.
     */
    public static function plugin(string $file, ?Platform $platform = null): self
    {
        return new self(self::PLUGIN, $file, $platform);
    }

    /**
     * Removes everything that the updater stored for a prefix: the saved token
     * and the stored state of the last check. Nothing else is touched.
     *
     * Call this from the `uninstall.php` file of a plugin. It doesn't register
     * anything, and it does nothing harmful when the options are already gone.
     * A token constant in `wp-config.php` belongs to the site owner, so it is
     * not removed, and the saved token is removed even when a constant is
     * defined.
     *
     * @param string $prefix The prefix that the plugin or theme was set up
     *     with.
     * @param Platform|null $platform The platform to run on. Tests pass a fake
     *     platform, and a WordPress site leaves it out.
     *
     * @throws InvalidArgumentException If the prefix isn't valid, in which case
     *     nothing is removed.
     */
    public static function uninstall(string $prefix, ?Platform $platform = null): void
    {
        $platform ??= WpPlatform::create();

        $tokens = new TokenStore($platform->options, $platform->constants, $prefix);
        $cache = new ReleaseCache($platform->options, $platform->clock, $prefix);

        $platform->options->delete($tokens->optionName());
        $cache->clear();
    }

    /**
     * Starts the setup for a theme.
     *
     * @param string $file The `functions.php` of the theme, which is `__FILE__`
     *     there.
     * @param Platform|null $platform The platform to run on. Tests pass a fake
     *     platform, and a WordPress site leaves it out.
     */
    public static function theme(string $file, ?Platform $platform = null): self
    {
        return new self(self::THEME, $file, $platform);
    }

    /**
     * @param string $repository The repository that the releases are in,
     *     written `owner/repository`.
     */
    public function fromGitHub(string $repository): self
    {
        $this->repository = $repository;

        return $this;
    }

    /**
     * @param string $prefix Lowercase letters, digits, and underscores, starting with a letter.
     */
    public function prefix(string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * @param string $capability The capability that is required to manage the
     *     update settings.
     *
     * @throws InvalidArgumentException If the capability is empty or has a
     *     character other than letters, digits, underscores, or hyphens.
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
     * @param string $pattern The pattern that the file name of the release
     *     asset must match, such as `front-desk-crm-*.zip`. It is a name, not a
     *     path.
     *
     * @throws InvalidArgumentException If the pattern is empty or has a slash.
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
     * Builds the updater and registers its hooks. A second call on the same
     * builder returns the updater from the first call.
     *
     * @return PackageUpdater The updater for the plugin or theme.
     *
     * @throws LogicException If the repository or the prefix wasn't set.
     * @throws InvalidArgumentException If a setting isn't valid.
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
            $package = PluginPackage::fromFile($this->file, is_string($pluginDir) ? $pluginDir : null);
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
}
