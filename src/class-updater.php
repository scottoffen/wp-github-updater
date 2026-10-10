<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater;

use InvalidArgumentException;
use LogicException;
use ScottOffen\WpGithubUpdater\Github\GitHubClient;
use ScottOffen\WpGithubUpdater\Package\HeaderReader;
use ScottOffen\WpGithubUpdater\Package\NotesPage;
use ScottOffen\WpGithubUpdater\Package\PackageDownloader;
use ScottOffen\WpGithubUpdater\Package\PackageUpdater;
use ScottOffen\WpGithubUpdater\Package\PluginPackage;
use ScottOffen\WpGithubUpdater\Package\PluginUpdater;
use ScottOffen\WpGithubUpdater\Package\ReleaseNotes;
use ScottOffen\WpGithubUpdater\Package\ThemePackage;
use ScottOffen\WpGithubUpdater\Package\ThemeUpdater;
use ScottOffen\WpGithubUpdater\Platform\Platform;
use ScottOffen\WpGithubUpdater\Release\ReleaseResolver;
use ScottOffen\WpGithubUpdater\Settings\Settings;
use ScottOffen\WpGithubUpdater\Settings\SettingsView;
use ScottOffen\WpGithubUpdater\Token\TokenStore;
use ScottOffen\WpGithubUpdater\Update\ReleaseCache;
use ScottOffen\WpGithubUpdater\Update\UpdateChecker;
use ScottOffen\WpGithubUpdater\WordPress\WpPlatform;

/**
 * Sets up a plugin or a theme to update itself from GitHub releases.
 *
 *     // In the main file of a plugin
 *     Updater::plugin(__FILE__)
 *         ->fromGitHub('owner/repository')
 *         ->prefix('fdcrm')
 *         ->register();
 *
 *     // In the functions.php of a theme
 *     Updater::theme(__FILE__)
 *         ->fromGitHub('owner/repository')
 *         ->prefix('mytheme')
 *         ->register();
 *
 * The repository and the prefix are required. The prefix picks the names of the
 * token constant and the options, so two plugins never share a token or a
 * cache. The capability defaults to `manage_options`, and the asset pattern to
 * the slug followed by `-*.zip`.
 *
 * When a plugin is deleted, its `uninstall.php` can remove what the updater
 * stored:
 *
 *     require __DIR__ . '/vendor/autoload.php';
 *
 *     ScottOffen\WpGithubUpdater\Updater::uninstall('fdcrm');
 *
 * A mistake in the setup throws when `register` runs, which is on the first
 * page load of the plugin that has it, so it shows up while the plugin is being
 * built. Calling `register` twice for the same plugin registers its hooks
 * twice, so it should be called once.
 */
final class Updater
{
    private const PLUGIN = 'plugin';
    private const THEME = 'theme';

    private string $repository = '';

    private string $prefix = '';

    private string $capability = 'manage_options';

    private ?string $assetPattern = null;

    private function __construct(
        private readonly string $kind,
        private readonly string $file,
        private readonly ?Platform $platform,
    ) {
    }

    /**
     * Starts the setup for a plugin.
     *
     * @param string $file The main file of the plugin, which is `__FILE__` there.
     * @param Platform|null $platform The platform to run on. Leave it out on a
     *     WordPress site. It is there so tests can supply their own.
     */
    public static function plugin(string $file, ?Platform $platform = null): self
    {
        return new self(self::PLUGIN, $file, $platform);
    }

    /**
     * Removes everything the updater stored for a prefix: the saved token and
     * the stored state of the last check. Nothing else is touched.
     *
     * A plugin calls this from its `uninstall.php`. It doesn't register
     * anything, and it does nothing harmful when the options are already
     * gone. A token constant in `wp-config.php` belongs to the site owner, so
     * it is left alone, and the saved token is removed even when a constant
     * would have taken its place.
     *
     * @param string $prefix The prefix the plugin or theme was set up with.
     * @param Platform|null $platform The platform to run on. Leave it out on a
     *     WordPress site. It is there so tests can supply their own.
     *
     * @throws InvalidArgumentException If the prefix isn't valid, in which case nothing is removed.
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
     * @param string $file The `functions.php` of the theme, which is `__FILE__` there.
     * @param Platform|null $platform The platform to run on. Leave it out on a
     *     WordPress site. It is there so tests can supply their own.
     */
    public static function theme(string $file, ?Platform $platform = null): self
    {
        return new self(self::THEME, $file, $platform);
    }

    /**
     * @param string $repository The repository the releases are in, written `owner/name`.
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
     * @param string $capability The capability needed to manage the update settings.
     *
     * @throws InvalidArgumentException If the capability is empty or has odd characters.
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
     * @param string $pattern The pattern the file name of the release asset must
     *     match, such as `front-desk-crm-*.zip`. It is a name, not a path.
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
     * Builds the updater and registers its hooks.
     *
     * @return PluginUpdater|ThemeUpdater A plugin updater for a plugin and a
     *     theme updater for a theme. Both share the `PackageUpdater` contract.
     *
     * @throws LogicException If the repository or the prefix wasn't set.
     * @throws InvalidArgumentException If a setting isn't valid.
     */
    public function register(): PluginUpdater|ThemeUpdater
    {
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
        $settingsFactory = static fn (PackageUpdater $updater): Settings => new Settings(
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

        return $updater;
    }
}
