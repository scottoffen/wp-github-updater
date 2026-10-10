<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Http\HttpResponse;
use ScottOffen\WpGithubUpdater\Package\PackageUpdater;
use ScottOffen\WpGithubUpdater\Package\PluginUpdater;
use ScottOffen\WpGithubUpdater\Package\ThemeUpdater;
use ScottOffen\WpGithubUpdater\Tests\Support\FakePlatform;
use ScottOffen\WpGithubUpdater\Tests\Support\WpStub;
use ScottOffen\WpGithubUpdater\Token\TokenStore;
use ScottOffen\WpGithubUpdater\Updater;

final class UpdaterTest extends TestCase
{
    private const FILE = '/wp/wp-content/plugins/my-plugin/my-plugin.php';

    private FakePlatform $world;

    protected function setUp(): void
    {
        WpStub::reset();
        $this->world = new FakePlatform();
    }

    private function updater(string $file = self::FILE): Updater
    {
        return Updater::plugin($file, $this->world->platform());
    }

    private function complete(): Updater
    {
        return $this->updater()->fromGitHub('owner/repo')->prefix('myplugin');
    }

    /** Builds the JSON GitHub sends for one stable release whose asset has the given name. */
    private function listing(string $assetName): HttpResponse
    {
        return new HttpResponse(200, ['ETag' => 'W/"e1"'], (string) json_encode([[
            'tag_name' => 'v1.0.0',
            'draft' => false,
            'prerelease' => false,
            'body' => '',
            'published_at' => '2026-10-01T12:00:00Z',
            'html_url' => '',
            'assets' => [[
                'id' => 7,
                'name' => $assetName,
                'url' => 'https://api.github.com/repos/owner/repo/releases/assets/7',
                'size' => 10,
                'digest' => '',
            ]],
        ]]));
    }

    public function testASetupWithARepositoryAndAPrefixBuildsAPluginUpdater(): void
    {
        $updater = $this->complete()->register();

        $this->assertInstanceOf(PluginUpdater::class, $updater);
        $this->assertInstanceOf(PackageUpdater::class, $updater);
        $this->assertSame('my-plugin', $updater->slug());
        $this->assertSame('owner/repo', $updater->repository());
        $this->assertSame('MYPLUGIN_GITHUB_TOKEN', $updater->tokenConstant());
        $this->assertInstanceOf(TokenStore::class, $updater->tokens());
        $this->assertSame('manage_options', $updater->capability());
    }

    public function testEverySetterReturnsTheSameBuilder(): void
    {
        $updater = $this->updater();

        $this->assertSame($updater, $updater->fromGitHub('owner/repo'));
        $this->assertSame($updater, $updater->prefix('myplugin'));
        $this->assertSame($updater, $updater->capability('update_plugins'));
        $this->assertSame($updater, $updater->assetPattern('my-plugin-*.zip'));
    }

    public function testTheCapabilityCanBeChanged(): void
    {
        $this->assertSame('update_plugins', $this->complete()->capability('update_plugins')->register()->capability());
    }

    public function testRegisterAddsTheHooksToThePlatform(): void
    {
        $this->complete()->register();

        foreach (['update_plugins_github.com', 'plugins_api', 'admin_head', 'upgrader_pre_download', 'upgrader_source_selection'] as $hook) {
            $this->assertCount(1, $this->world->hooks->registered($hook), $hook);
        }
    }

    public function testTheRepositoryAndThePrefixAreRequired(): void
    {
        foreach ([
            [fn (Updater $u): Updater => $u->prefix('myplugin'), 'fromGitHub'],
            [fn (Updater $u): Updater => $u->fromGitHub('owner/repo'), 'prefix'],
            [fn (Updater $u): Updater => $u, 'fromGitHub'],
        ] as [$setup, $missing]) {
            $world = new FakePlatform();

            try {
                $setup(Updater::plugin(self::FILE, $world->platform()))->register();
                $this->fail('Registered without ' . $missing);
            } catch (LogicException $e) {
                $this->assertSame(LogicException::class, $e::class, $missing);
                $this->assertSame('Call ' . $missing . '() before register().', $e->getMessage());
            }

            $this->assertSame([], $world->hooks->registered('plugins_api'));
        }
    }

    public function testAnInvalidSettingThrowsAndRegistersNothing(): void
    {
        $bad = [
            'repository' => fn (Updater $u): Updater => $u->fromGitHub('not a repository')->prefix('myplugin'),
            'prefix' => fn (Updater $u): Updater => $u->fromGitHub('owner/repo')->prefix('My-Plugin'),
        ];

        foreach ($bad as $label => $setup) {
            $world = new FakePlatform();

            try {
                $setup(Updater::plugin(self::FILE, $world->platform()))->register();
                $this->fail('Registered with an invalid ' . $label);
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }

            $this->assertSame([], $world->hooks->registered('plugins_api'), $label);
        }
    }

    public function testAnInvalidCapabilityThrowsWhenItIsSet(): void
    {
        foreach (['', 'manage options', 'a;b', "a\n", 'caf' . "\u{e9}"] as $capability) {
            try {
                $this->updater()->capability($capability);
                $this->fail('Accepted ' . var_export($capability, true));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('edit_theme_options', $this->updater()->fromGitHub('o/r')->prefix('p')->capability('edit_theme_options')->register()->capability());
        $this->assertSame('my-custom-cap', $this->updater()->fromGitHub('o/r')->prefix('p')->capability('my-custom-cap')->register()->capability());
    }

    public function testAnInvalidAssetPatternThrowsWhenItIsSet(): void
    {
        foreach (['', 'dir/my-plugin-*.zip', 'dir\\my-plugin-*.zip', '/my-plugin.zip'] as $pattern) {
            try {
                $this->updater()->assetPattern($pattern);
                $this->fail('Accepted ' . var_export($pattern, true));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testASingleFilePluginIsRejectedAtRegister(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->updater(FakePlatform::PLUGIN_DIR . '/hello.php')->fromGitHub('owner/repo')->prefix('hello')->register();
    }

    public function testThePluginsFolderCheckIsSkippedWhenTheSiteDoesNotDefineIt(): void
    {
        $world = new FakePlatform();
        $platform = new \ScottOffen\WpGithubUpdater\Platform\Platform(
            $world->clock,
            $world->options,
            new \ScottOffen\WpGithubUpdater\Tests\Support\FakeConstants(),
            $world->files,
            $world->hooks,
            $world->http,
            $world->sanitizer,
            $world->updateData,
            $world->messages,
            $world->admin,
            $world->settingsText,
        );

        $updater = Updater::plugin(FakePlatform::PLUGIN_DIR . '/hello.php', $platform)->fromGitHub('o/r')->prefix('hello')->register();

        $this->assertSame('plugins', $updater->slug());
    }

    public function testTheDefaultAssetPatternIsTheSlugFollowedByAWildcard(): void
    {
        $this->complete()->register();
        $this->world->http->queue($this->listing('my-plugin-1.0.0.zip'));

        $update = $this->world->hooks->applyFilters('update_plugins_github.com', false, [], 'my-plugin/my-plugin.php', []);

        $this->assertIsArray($update);
        $this->assertSame('1.0.0', $update['version']);
    }

    public function testTheDefaultAssetPatternDoesNotAcceptAnotherName(): void
    {
        $this->complete()->register();
        $this->world->http->queue($this->listing('front-desk-crm-1.0.0.zip'));

        $this->assertFalse($this->world->hooks->applyFilters('update_plugins_github.com', false, [], 'my-plugin/my-plugin.php', []));
    }

    public function testACustomAssetPatternReplacesTheDefault(): void
    {
        $this->complete()->assetPattern('front-desk-crm-*.zip')->register();
        $this->world->http->queue($this->listing('front-desk-crm-1.0.0.zip'));

        $update = $this->world->hooks->applyFilters('update_plugins_github.com', false, [], 'my-plugin/my-plugin.php', []);

        $this->assertIsArray($update);
        $this->assertSame('1.0.0', $update['version']);
    }

    private const THEME_FILE = '/wp/wp-content/themes/my-theme/functions.php';

    private function theme(string $file = self::THEME_FILE): Updater
    {
        return Updater::theme($file, $this->world->platform())->fromGitHub('owner/repo')->prefix('mytheme');
    }

    public function testASetupForAThemeBuildsAThemeUpdater(): void
    {
        $updater = $this->theme()->register();

        $this->assertInstanceOf(ThemeUpdater::class, $updater);
        $this->assertNotInstanceOf(PluginUpdater::class, $updater);
        $this->assertInstanceOf(PackageUpdater::class, $updater);
        $this->assertSame('my-theme', $updater->slug());
        $this->assertSame('owner/repo', $updater->repository());
        $this->assertSame('MYTHEME_GITHUB_TOKEN', $updater->tokenConstant());
        $this->assertSame('manage_options', $updater->capability());
    }

    public function testEverySetterWorksForAThemeToo(): void
    {
        $updater = $this->theme();

        $this->assertSame($updater, $updater->capability('edit_theme_options'));
        $this->assertSame($updater, $updater->assetPattern('my-theme-*.zip'));
        $this->assertSame('edit_theme_options', $updater->register()->capability());
    }

    public function testRegisterAddsTheThemeHooksAndNotThePluginHooks(): void
    {
        $this->theme()->register();

        foreach (['update_themes_github.com', 'admin_post_mytheme_updater_notes', 'upgrader_pre_download', 'upgrader_source_selection'] as $hook) {
            $this->assertCount(1, $this->world->hooks->registered($hook), $hook);
        }

        $this->assertSame([], $this->world->hooks->registered('update_plugins_github.com'));
        $this->assertSame([], $this->world->hooks->registered('plugins_api'));
    }

    public function testTheNotesAddressOfAThemeIsNamedWithThePrefix(): void
    {
        $this->theme()->register();
        $this->world->http->queue($this->listing('my-theme-1.0.0.zip'));

        $update = $this->world->hooks->applyFilters('update_themes_github.com', false, [], 'my-theme', []);

        $this->assertIsArray($update);
        $this->assertSame('https://example.test/wp-admin/admin-post.php?action=mytheme_updater_notes', $update['url']);
    }

    public function testThemeSetupNeedsTheRepositoryAndThePrefixToo(): void
    {
        foreach ([
            [fn (Updater $u): Updater => $u->prefix('mytheme'), 'fromGitHub'],
            [fn (Updater $u): Updater => $u->fromGitHub('owner/repo'), 'prefix'],
        ] as [$setup, $missing]) {
            $world = new FakePlatform();

            try {
                $setup(Updater::theme(self::THEME_FILE, $world->platform()))->register();
                $this->fail('Registered without ' . $missing);
            } catch (LogicException $e) {
                $this->assertSame(LogicException::class, $e::class, $missing);
                $this->assertSame('Call ' . $missing . '() before register().', $e->getMessage());
            }

            $this->assertSame([], $world->hooks->registered('update_themes_github.com'));
        }
    }

    public function testAThemeFileThatIsNotFunctionsPhpThrowsAndRegistersNothing(): void
    {
        foreach (['/wp/wp-content/themes/my-theme/inc/updater.php', '/wp/wp-content/themes/my-theme/style.css'] as $file) {
            $world = new FakePlatform();

            try {
                Updater::theme($file, $world->platform())->fromGitHub('owner/repo')->prefix('mytheme')->register();
                $this->fail('Registered ' . $file);
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('functions.php', $e->getMessage());
            }

            $this->assertSame([], $world->hooks->registered('update_themes_github.com'));
        }
    }

    public function testAnInvalidRepositoryOrPrefixThrowsForAThemeToo(): void
    {
        foreach ([
            fn (Updater $u): Updater => $u->fromGitHub('not a repository')->prefix('mytheme'),
            fn (Updater $u): Updater => $u->fromGitHub('owner/repo')->prefix('My-Theme'),
        ] as $setup) {
            $world = new FakePlatform();

            try {
                $setup(Updater::theme(self::THEME_FILE, $world->platform()))->register();
                $this->fail('Registered with an invalid setting');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }

            $this->assertSame([], $world->hooks->registered('update_themes_github.com'));
        }
    }

    public function testAThemeIsNotCheckedAgainstThePluginsFolder(): void
    {
        $world = new FakePlatform();
        $world->constants->define('WP_CONTENT_DIR', '/wp/wp-content');

        $updater = Updater::theme('/wp/wp-content/plugins/functions.php', $world->platform())->fromGitHub('o/r')->prefix('mytheme')->register();

        $this->assertSame('plugins', $updater->slug());
    }

    public function testTheDefaultAssetPatternOfAThemeIsTheSlugFollowedByAWildcard(): void
    {
        $this->theme()->register();

        $this->world->http->queue($this->listing('my-theme-1.0.0.zip'));
        $match = $this->world->hooks->applyFilters('update_themes_github.com', false, [], 'my-theme', []);

        $this->assertIsArray($match);
        $this->assertSame('1.0.0', $match['version']);

        $world = new FakePlatform();
        Updater::theme(self::THEME_FILE, $world->platform())->fromGitHub('owner/repo')->prefix('mytheme')->register();
        $world->http->queue($this->listing('other-theme-1.0.0.zip'));

        $this->assertFalse($world->hooks->applyFilters('update_themes_github.com', false, [], 'my-theme', []));
    }

    public function testWithNoPlatformGivenTheWordPressOneIsUsedForAThemeToo(): void
    {
        $updater = Updater::theme(self::THEME_FILE)->fromGitHub('owner/repo')->prefix('mytheme')->register();

        $this->assertSame('my-theme', $updater->slug());
        $this->assertSame(
            [
                'update_themes_github.com',
                'admin_post_mytheme_updater_notes',
                'upgrader_pre_download',
                'upgrader_source_selection',
                'admin_post_mytheme_updater_save',
                'admin_post_mytheme_updater_remove',
                'admin_post_mytheme_updater_check',
            ],
            array_column(WpStub::$hooks, 'hook'),
        );
    }

    public function testUninstallingRemovesTheSavedTokenAndTheStoredStateOfThatPrefix(): void
    {
        $this->world->options->set('myplugin_updater_github_token', 'a-saved-token-value');
        $this->world->options->set('myplugin_updater_release_state', ['v' => 1]);

        Updater::uninstall('myplugin', $this->world->platform());

        $this->assertFalse($this->world->options->has('myplugin_updater_github_token'));
        $this->assertFalse($this->world->options->has('myplugin_updater_release_state'));
        $this->assertSame([], $this->world->options->names());
    }

    public function testUninstallingTouchesNothingButThosePluginOptions(): void
    {
        $this->world->options->set('myplugin_updater_github_token', 'a-saved-token-value');
        $this->world->options->set('myplugin_updater_release_state', ['v' => 1]);
        $this->world->options->set('otherplugin_updater_github_token', 'another-plugins-token');
        $this->world->options->set('otherplugin_updater_release_state', ['v' => 1]);
        $this->world->options->set('myplugin_settings', ['mine' => true]);
        $this->world->options->set('myplugin_updater_github_token_backup', 'not ours');
        $this->world->options->set('siteurl', 'https://example.test');

        Updater::uninstall('myplugin', $this->world->platform());

        $this->assertSame([
            'otherplugin_updater_github_token',
            'otherplugin_updater_release_state',
            'myplugin_settings',
            'myplugin_updater_github_token_backup',
            'siteurl',
        ], $this->world->options->names());
        $this->assertSame('another-plugins-token', $this->world->options->get('otherplugin_updater_github_token'));
    }

    public function testUninstallingWhenNothingIsStoredIsFine(): void
    {
        Updater::uninstall('myplugin', $this->world->platform());
        Updater::uninstall('myplugin', $this->world->platform());

        $this->assertSame([], $this->world->options->names());
    }

    public function testUninstallingRemovesTheSavedTokenEvenWhenAConstantSuppliesTheToken(): void
    {
        $this->world->constants->define('MYPLUGIN_GITHUB_TOKEN', 'from-the-constant');
        $this->world->options->set('myplugin_updater_github_token', 'a-saved-token-value');

        Updater::uninstall('myplugin', $this->world->platform());

        $this->assertFalse($this->world->options->has('myplugin_updater_github_token'));
        $this->assertSame('from-the-constant', $this->world->constants->get('MYPLUGIN_GITHUB_TOKEN'));
    }

    public function testUninstallingRegistersNothingAndMakesNoRequest(): void
    {
        $this->world->options->set('myplugin_updater_github_token', 'a-saved-token-value');

        Updater::uninstall('myplugin', $this->world->platform());

        foreach (['update_plugins_github.com', 'plugins_api', 'admin_head', 'upgrader_pre_download', 'upgrader_source_selection', 'admin_post_myplugin_updater_save'] as $hook) {
            $this->assertSame([], $this->world->hooks->registered($hook), $hook);
        }

        $this->assertSame([], $this->world->http->requests());
        $this->assertSame(0, $this->world->updateData->forgotten());
    }

    public function testUninstallingWithAnInvalidPrefixThrowsAndRemovesNothing(): void
    {
        $this->world->options->set('My-Plugin_updater_github_token', 'not ours');
        $this->world->options->set('myplugin_updater_github_token', 'a-saved-token-value');

        foreach (['', 'My-Plugin', 'MyPlugin', '1plugin', '_plugin', 'my plugin', "myplugin\n"] as $prefix) {
            try {
                Updater::uninstall($prefix, $this->world->platform());
                $this->fail('Accepted ' . var_export($prefix, true));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(['My-Plugin_updater_github_token', 'myplugin_updater_github_token'], $this->world->options->names());
    }

    public function testUninstallingWorksTheSameForAThemePrefix(): void
    {
        $this->world->options->set('mytheme_updater_github_token', 'a-saved-token-value');
        $this->world->options->set('mytheme_updater_release_state', ['v' => 1]);

        Updater::uninstall('mytheme', $this->world->platform());

        $this->assertSame([], $this->world->options->names());
    }

    public function testWithNoPlatformGivenUninstallingUsesTheWordPressOptions(): void
    {
        WpStub::$options['fdcrm_updater_github_token'] = 'a-saved-token-value';
        WpStub::$options['fdcrm_updater_release_state'] = ['v' => 1];
        WpStub::$options['fdcrm_other'] = 'kept';

        Updater::uninstall('fdcrm');

        $this->assertSame(['fdcrm_other' => 'kept'], WpStub::$options);
    }

    public function testWithNoPlatformGivenTheWordPressOneIsUsed(): void
    {
        $updater = Updater::plugin('/wp/wp-content/plugins/my-plugin/my-plugin.php')->fromGitHub('owner/repo')->prefix('myplugin')->register();

        $this->assertSame('my-plugin', $updater->slug());

        $hooks = array_column(WpStub::$hooks, 'hook');

        $this->assertSame([
            'update_plugins_github.com',
            'plugins_api',
            'admin_head',
            'upgrader_pre_download',
            'upgrader_source_selection',
            'admin_post_myplugin_updater_save',
            'admin_post_myplugin_updater_remove',
            'admin_post_myplugin_updater_check',
        ], $hooks);
    }
}
