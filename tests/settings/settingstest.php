<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Settings;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Internal\Http\HttpResponse;
use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;
use ScottOffen\WpGithubUpdater\Internal\Settings\Notice;
use ScottOffen\WpGithubUpdater\PackageUpdater;
use ScottOffen\WpGithubUpdater\Settings;
use ScottOffen\WpGithubUpdater\Tests\Support\FakePlatform;
use ScottOffen\WpGithubUpdater\Updater;

final class SettingsTest extends TestCase
{
    private const PLUGIN_FILE = FakePlatform::PLUGIN_DIR . '/my-plugin/my-plugin.php';
    private const THEME_FUNCTIONS = '/wp/wp-content/themes/my-theme/functions.php';
    private const OPTION = 'fdcrm_updater_github_token';
    private const SECRET = 'ghp_secretvalue123';
    private const NOTICE = 'fdcrm_updater_notice';

    private FakePlatform $world;

    private PackageUpdater $plugin;

    protected function setUp(): void
    {
        $this->world = new FakePlatform();
        $this->world->files->put(self::PLUGIN_FILE, "<?php\n/* Plugin Name: My Plugin\n * Version: 0.9.0 */\n");
        $this->world->admin->allow('manage_options');
        $this->plugin = $this->buildPlugin();
    }

    private function buildPlugin(string $prefix = 'fdcrm', string $file = self::PLUGIN_FILE, ?string $capability = null): PackageUpdater
    {
        $updater = Updater::plugin($file, $this->world->platform())->fromGitHub('owner/repo')->prefix($prefix);

        return ($capability === null ? $updater : $updater->capability($capability))->register();
    }

    private function listing(string $slug = 'my-plugin', string $tag = 'v1.0.0'): HttpResponse
    {
        return new HttpResponse(200, ['ETag' => 'W/"e1"'], (string) json_encode([[
            'tag_name' => $tag,
            'draft' => false,
            'prerelease' => false,
            'body' => '',
            'published_at' => '2026-10-01T12:00:00Z',
            'html_url' => '',
            'assets' => [[
                'id' => 7,
                'name' => $slug . '-' . ltrim($tag, 'v') . '.zip',
                'url' => 'https://api.github.com/repos/owner/repo/releases/assets/7',
                'size' => 16,
                'digest' => '',
            ]],
        ]]));
    }

    private function post(string $action, string $prefix = 'fdcrm'): void
    {
        $this->world->hooks->doAction('admin_post_' . $prefix . '_updater_' . $action);
    }

    private function section(?PackageUpdater $updater = null, string $heading = ''): string
    {
        ob_start();
        ($updater ?? $this->plugin)->settings()->render($heading);

        return (string) ob_get_clean();
    }

    /** @return list<array{string, string}> */
    private function redirects(): array
    {
        return $this->world->admin->redirects();
    }

    public function testTheSettingsAreTheSameObjectEveryTime(): void
    {
        $this->assertInstanceOf(Settings::class, $this->plugin->settings());
        $this->assertSame($this->plugin->settings(), $this->plugin->settings());
    }

    public function testTheHandlersAreRegisteredWhenThePackageRegistersWithoutCallingSettings(): void
    {
        foreach (['save', 'remove', 'check'] as $name) {
            $entries = $this->world->hooks->registered('admin_post_fdcrm_updater_' . $name);

            $this->assertCount(1, $entries, $name);
            $this->assertSame('action', $entries[0]['type'], $name);
            $this->assertSame(10, $entries[0]['priority'], $name);
        }

        $this->assertSame([], $this->world->hooks->registered('admin_menu'));
    }

    public function testTheSectionShowsTheFormsTheStatusAndTheHeading(): void
    {
        $html = $this->section(null, 'Plugin Updates');

        $this->assertStringContainsString('<h2>Plugin Updates</h2>', $html);

        foreach (['save', 'remove', 'check'] as $name) {
            if ($name === 'remove') {
                $this->assertStringNotContainsString('admin-post.php?action=fdcrm_updater_remove', $html);
                continue;
            }

            $this->assertStringContainsString('action="https://example.test/wp-admin/admin-post.php?action=fdcrm_updater_' . $name . '"', $html, $name);
            $this->assertStringContainsString('value="nonce:fdcrm_updater_' . $name . '"', $html, $name);
        }

        $this->assertStringContainsString('Never checked Installed 0.9.0', $html);
    }

    public function testTheSectionShowsNothingToSomeoneWithoutTheCapability(): void
    {
        $world = new FakePlatform();
        $world->files->put(self::PLUGIN_FILE, "<?php\n");
        $plugin = Updater::plugin(self::PLUGIN_FILE, $world->platform())->fromGitHub('owner/repo')->prefix('fdcrm')->register();
        $world->admin->allow('edit_posts', 'read');

        ob_start();
        $plugin->settings()->render('Updates');

        $this->assertSame('', ob_get_clean());
    }

    public function testTheStatusAfterACheckShowsTheTimeAndTheLatestVersion(): void
    {
        $this->world->http->queue($this->listing());
        $this->plugin->checkNow();

        $html = $this->section();

        $this->assertStringContainsString('Last checked T1800000000 Installed 0.9.0 Latest 1.0.0', $html);
    }

    public function testAFailedCheckShowsItsProblemOnTheStatusLine(): void
    {
        $this->world->http->queue(new HttpResponse(401));
        $this->plugin->checkNow();

        $this->assertStringContainsString('<span class="wp-github-updater-problem">Problem ' . Outcome::AUTH . '</span>', $this->section());
    }

    public function testANoticeInTheAddressIsShownOnlyWhenItIsOnTheList(): void
    {
        $this->world->admin->withQuery(self::NOTICE, 'saved');
        $this->assertStringContainsString('Notice saved', $this->section());

        $world = new FakePlatform();
        $world->files->put(self::PLUGIN_FILE, "<?php\n");
        $world->admin->allow('manage_options');
        $world->admin->withQuery(self::NOTICE, '<script>alert(1)</script>');
        $plugin = Updater::plugin(self::PLUGIN_FILE, $world->platform())->fromGitHub('owner/repo')->prefix('fdcrm')->register();

        $html = $this->section($plugin);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('class="notice', $html);
    }

    public function testASavedTokenShowsOnlyMaskedAndGetsARemoveForm(): void
    {
        $this->plugin->tokens()->save(self::SECRET);

        $html = $this->section();

        $this->assertStringNotContainsString(self::SECRET, $html);
        $this->assertStringContainsString('placeholder="' . str_repeat("\u{2022}", 8) . 'e123"', $html);
        $this->assertStringContainsString('admin-post.php?action=fdcrm_updater_remove', $html);
    }

    public function testATokenWhoseLastCharactersAreMarkupCannotBreakOutOfThePlaceholder(): void
    {
        $this->assertTrue($this->plugin->tokens()->save('ghp_abcdefgh"><b>'));

        $html = $this->section();

        $this->assertStringContainsString('placeholder="' . str_repeat("\u{2022}", 8) . '&gt;&lt;b&gt;"', $html);
        $this->assertStringNotContainsString('"><b>', $html);
    }

    public function testAConstantLocksTheSectionAndShowsItsName(): void
    {
        $this->world->constants->define('FDCRM_GITHUB_TOKEN', self::SECRET);

        $html = $this->section();

        $this->assertStringContainsString('value="FDCRM_GITHUB_TOKEN"', $html);
        $this->assertStringContainsString('Set by FDCRM_GITHUB_TOKEN', $html);
        $this->assertStringNotContainsString(self::SECRET, $html);
        $this->assertStringNotContainsString('type="password"', $html);
        $this->assertStringNotContainsString('admin-post.php?action=fdcrm_updater_save', $html);
        $this->assertStringContainsString('admin-post.php?action=fdcrm_updater_check', $html);
    }

    public function testSavingAValidTokenStoresItChecksForUpdatesAndGoesBackWithANotice(): void
    {
        $this->world->admin->withInput('fdcrm_updater_token', '  ' . self::SECRET . "\n");
        $this->world->http->queue($this->listing());

        $this->post('save');

        $this->assertSame(self::SECRET, $this->world->options->get(self::OPTION));
        $this->assertFalse($this->world->options->autoloads(self::OPTION));
        $this->assertCount(1, $this->world->http->requests());
        $this->assertSame('Bearer ' . self::SECRET, $this->world->http->requests()[0]['headers']['Authorization']);
        $this->assertArrayNotHasKey('If-None-Match', $this->world->http->requests()[0]['headers']);
        $this->assertSame('1.0.0', $this->plugin->state()->release?->version);
        $this->assertSame([[self::NOTICE, Notice::SAVED]], $this->redirects());
        $this->assertSame(['fdcrm_updater_save'], $this->world->admin->verified());
        $this->assertSame(1, $this->world->admin->terminated());
        $this->assertSame(1, $this->world->updateData->forgotten());
    }

    public function testTheTokenNeverAppearsInTheRedirectOrTheSectionAfterSaving(): void
    {
        $this->world->admin->withInput('fdcrm_updater_token', self::SECRET);
        $this->world->http->queue($this->listing());

        $this->post('save');

        $this->assertStringNotContainsString(self::SECRET, (string) json_encode($this->redirects()));
        $this->assertStringNotContainsString(self::SECRET, $this->section());
        $this->assertStringNotContainsString(self::SECRET, (string) json_encode($this->world->admin->responses()));
    }

    public function testSavingATokenGitHubRejectsStillSavesItAndShowsTheProblemOnTheStatusLine(): void
    {
        $this->world->admin->withInput('fdcrm_updater_token', self::SECRET);
        $this->world->http->queue(new HttpResponse(401));

        $this->post('save');

        $this->assertSame([[self::NOTICE, Notice::SAVED]], $this->redirects());
        $this->assertSame(Outcome::AUTH, $this->plugin->state()->outcome);
        $this->assertStringContainsString('Problem ' . Outcome::AUTH, $this->section());
    }

    public function testSavingAnEmptyTokenChangesNothingAndMakesNoRequest(): void
    {
        $this->plugin->tokens()->save('an-earlier-token');

        foreach (['', '   ', "\n\t"] as $input) {
            $this->world->admin->withInput('fdcrm_updater_token', $input);

            $this->post('save');
        }

        $this->assertSame('an-earlier-token', $this->world->options->get(self::OPTION));
        $this->assertSame([], $this->world->http->requests());
        $this->assertSame(array_fill(0, 3, [self::NOTICE, Notice::EMPTY]), $this->redirects());
    }

    public function testSavingATokenThatCannotBeSentInAHeaderIsRefused(): void
    {
        foreach (['has space', "line\nbreak", "caf\u{e9}"] as $input) {
            $this->world->admin->withInput('fdcrm_updater_token', $input);

            $this->post('save');
        }

        $this->assertFalse($this->world->options->has(self::OPTION));
        $this->assertSame([], $this->world->http->requests());
        $this->assertSame(array_fill(0, 3, [self::NOTICE, Notice::INVALID]), $this->redirects());
    }

    public function testSavingWhileAConstantSuppliesTheTokenChangesNothing(): void
    {
        $this->world->constants->define('FDCRM_GITHUB_TOKEN', 'from-the-constant');
        $this->world->admin->withInput('fdcrm_updater_token', self::SECRET);

        $this->post('save');

        $this->assertFalse($this->world->options->has(self::OPTION));
        $this->assertSame([], $this->world->http->requests());
        $this->assertSame([[self::NOTICE, Notice::LOCKED]], $this->redirects());
    }

    public function testRemovingTheTokenClearsItAndChecksForUpdates(): void
    {
        $this->plugin->tokens()->save(self::SECRET);
        $this->world->http->queue($this->listing());

        $this->post('remove');

        $this->assertFalse($this->world->options->has(self::OPTION));
        $this->assertCount(1, $this->world->http->requests());
        $this->assertArrayNotHasKey('Authorization', $this->world->http->requests()[0]['headers']);
        $this->assertSame([[self::NOTICE, Notice::REMOVED]], $this->redirects());
        $this->assertSame(['fdcrm_updater_remove'], $this->world->admin->verified());
        $this->assertSame(1, $this->world->admin->terminated());
    }

    public function testRemovingWithNoSavedTokenStillChecksAndShowsTheRemovedNotice(): void
    {
        $this->world->http->queue($this->listing());

        $this->post('remove');

        $this->assertCount(1, $this->world->http->requests());
        $this->assertSame([[self::NOTICE, Notice::REMOVED]], $this->redirects());
    }

    public function testRemovingWhileTheTokenComesFromAConstantLeavesTheSavedOneUnchanged(): void
    {
        $this->plugin->tokens()->save(self::SECRET);
        $this->world->constants->define('FDCRM_GITHUB_TOKEN', 'from-the-constant');

        $this->post('remove');

        $this->assertSame(self::SECRET, $this->world->options->get(self::OPTION));
        $this->assertSame([], $this->world->http->requests());
        $this->assertSame([[self::NOTICE, Notice::LOCKED]], $this->redirects());
    }

    public function testCheckingNowRunsAForcedCheckThatIgnoresTheCacheAndTheEtag(): void
    {
        $this->world->http->queue($this->listing(), $this->listing(tag: 'v1.1.0'));
        $this->plugin->checkNow();

        $this->post('check');

        $requests = $this->world->http->requests();

        $this->assertCount(2, $requests);
        $this->assertArrayNotHasKey('If-None-Match', $requests[1]['headers']);
        $this->assertSame('1.1.0', $this->plugin->state()->release?->version);
        $this->assertSame([[self::NOTICE, Notice::CHECKED]], $this->redirects());
        $this->assertSame(['fdcrm_updater_check'], $this->world->admin->verified());
        $this->assertSame(1, $this->world->admin->terminated());
    }

    public function testCheckingNowDiscardsTheStoredPluginUpdateData(): void
    {
        $this->world->http->queue($this->listing());

        $this->post('check');

        $this->assertSame(1, $this->world->updateData->forgotten());
        $this->assertSame(0, $this->world->updateData->themesForgotten());
    }

    public function testEveryHandlerRefusesSomeoneWithoutTheCapabilityBeforeDoingAnythingElse(): void
    {
        $world = new FakePlatform();
        $world->files->put(self::PLUGIN_FILE, "<?php\n");
        $world->admin->allow('edit_posts', 'read', 'update_themes');
        $plugin = Updater::plugin(self::PLUGIN_FILE, $world->platform())->fromGitHub('owner/repo')->prefix('fdcrm')->register();
        $plugin->tokens()->save('an-earlier-token');
        $world->admin->withInput('fdcrm_updater_token', self::SECRET);

        foreach (['save', 'remove', 'check'] as $name) {
            $world->hooks->doAction('admin_post_fdcrm_updater_' . $name);
        }

        $this->assertSame(3, $world->admin->denied());
        $this->assertSame([], $world->admin->redirects());
        $this->assertSame(0, $world->admin->terminated());
        $this->assertSame([], $world->admin->verified());
        $this->assertSame([], $world->http->requests());
        $this->assertSame('an-earlier-token', $world->options->get(self::OPTION));
    }

    public function testEveryHandlerSendsAnExpiredFormBackWithANoticeAndDoesNothingElse(): void
    {
        $this->plugin->tokens()->save('an-earlier-token');
        $this->world->admin->withInput('fdcrm_updater_token', self::SECRET);
        $this->world->admin->invalidateNonces();

        foreach (['save', 'remove', 'check'] as $name) {
            $this->post($name);
        }

        $this->assertSame(array_fill(0, 3, [self::NOTICE, Notice::EXPIRED]), $this->redirects());
        $this->assertSame(['fdcrm_updater_save', 'fdcrm_updater_remove', 'fdcrm_updater_check'], $this->world->admin->verified());
        $this->assertSame(3, $this->world->admin->terminated());
        $this->assertSame(0, $this->world->admin->denied());
        $this->assertSame([], $this->world->http->requests());
        $this->assertSame('an-earlier-token', $this->world->options->get(self::OPTION));
    }

    public function testACustomCapabilityGuardsTheHandlersAndTheSection(): void
    {
        $world = new FakePlatform();
        $world->files->put(self::PLUGIN_FILE, "<?php\n");
        $plugin = Updater::plugin(self::PLUGIN_FILE, $world->platform())->fromGitHub('owner/repo')->prefix('fdcrm')->capability('update_plugins')->register();
        $world->admin->allow('manage_options');

        $world->hooks->doAction('admin_post_fdcrm_updater_check');
        ob_start();
        $plugin->settings()->render();

        $this->assertSame('', ob_get_clean());
        $this->assertSame(1, $world->admin->denied());

        $world->admin->allow('update_plugins');
        $world->http->queue($this->listing());
        $world->hooks->doAction('admin_post_fdcrm_updater_check');

        $this->assertSame(1, $world->admin->denied());
        $this->assertSame([['fdcrm_updater_notice', Notice::CHECKED]], $world->admin->redirects());
    }

    public function testAPageIsAddedToTheMenuOnlyWhenTheMenuIsBuilt(): void
    {
        $this->plugin->settings()->addPage('Updates', 'options-general.php');

        $entries = $this->world->hooks->registered('admin_menu');

        $this->assertCount(1, $entries);
        $this->assertSame(99, $entries[0]['priority']);
        $this->assertSame('action', $entries[0]['type']);
        $this->assertSame([], $this->world->admin->pages());

        $this->world->hooks->doAction('admin_menu');

        $this->assertCount(1, $this->world->admin->pages());

        $page = $this->world->admin->pages()[0];

        $this->assertSame('options-general.php', $page['parent']);
        $this->assertSame('Updates', $page['title']);
        $this->assertSame('manage_options', $page['capability']);
        $this->assertSame('fdcrm_updater_settings', $page['slug']);
    }

    public function testThePageWrapsTheSectionInAWrapWithTheTitleAsTheHeading(): void
    {
        $this->plugin->settings()->addPage('Updates & <More>', 'options-general.php');
        $this->world->hooks->doAction('admin_menu');

        ob_start();
        ($this->world->admin->pages()[0]['render'])();
        $html = (string) ob_get_clean();

        $this->assertStringStartsWith('<div class="wrap"><h1>Updates &amp; &lt;More&gt;</h1><div class="wp-github-updater">', $html);
        $this->assertStringEndsWith('</div></div>', $html);
        $this->assertStringNotContainsString('<h2>', $html);
        $this->assertStringContainsString('Never checked', $html);
    }

    public function testThePageUsesTheCapabilityTheBuilderWasGiven(): void
    {
        $world = new FakePlatform();
        $world->files->put(self::PLUGIN_FILE, "<?php\n");
        $plugin = Updater::plugin(self::PLUGIN_FILE, $world->platform())->fromGitHub('owner/repo')->prefix('fdcrm')->capability('update_plugins')->register();

        $plugin->settings()->addPage('Updates', 'options-general.php');
        $world->hooks->doAction('admin_menu');

        $this->assertSame('update_plugins', $world->admin->pages()[0]['capability']);
    }

    public function testAPageRequiresATitleAndAParentMenu(): void
    {
        foreach ([['', 'options-general.php'], ['  ', 'options-general.php'], ['Updates', ''], ['Updates', '   ']] as [$title, $parent]) {
            try {
                $this->plugin->settings()->addPage($title, $parent);
                $this->fail('Accepted ' . var_export([$title, $parent], true));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame([], $this->world->hooks->registered('admin_menu'));
    }

    public function testAThemeGetsTheSameSectionUnderItsOwnPrefix(): void
    {
        $this->world->files->put(self::THEME_FUNCTIONS, "<?php\n");
        $this->world->files->put('/wp/wp-content/themes/my-theme/style.css', "/*\nTheme Name: My Theme\nVersion: 2.0.0\n*/\n");
        $theme = Updater::theme(self::THEME_FUNCTIONS, $this->world->platform())->fromGitHub('owner/repo')->prefix('mytheme')->register();

        $theme->settings()->addPage('Updates', 'themes.php');
        $this->world->hooks->doAction('admin_menu');

        $this->assertSame('themes.php', $this->world->admin->pages()[0]['parent']);
        $this->assertSame('mytheme_updater_settings', $this->world->admin->pages()[0]['slug']);
        $this->assertStringContainsString('Installed 2.0.0', $this->section($theme));
        $this->assertStringContainsString('action=mytheme_updater_save', $this->section($theme));
    }

    public function testCheckingAThemeDiscardsTheStoredThemeUpdateData(): void
    {
        $this->world->files->put(self::THEME_FUNCTIONS, "<?php\n");
        Updater::theme(self::THEME_FUNCTIONS, $this->world->platform())->fromGitHub('owner/repo')->prefix('mytheme')->register();
        $this->world->http->queue($this->listing('my-theme'));

        $this->post('check', 'mytheme');

        $this->assertSame(1, $this->world->updateData->themesForgotten());
        $this->assertSame(0, $this->world->updateData->forgotten());
        $this->assertSame([['mytheme_updater_notice', Notice::CHECKED]], $this->redirects());
    }

    public function testTwoPackagesNeverShareAHandlerOrAToken(): void
    {
        $fileB = FakePlatform::PLUGIN_DIR . '/plugin-b/plugin-b.php';
        $this->world->files->put($fileB, "<?php\n");
        $pluginB = $this->buildPlugin('pluginb', $fileB);

        $this->world->admin->withInput('fdcrm_updater_token', 'token-for-a-only');
        $this->world->http->queue($this->listing());
        $this->post('save', 'fdcrm');

        $this->assertSame('token-for-a-only', $this->world->options->get('fdcrm_updater_github_token'));
        $this->assertFalse($this->world->options->has('pluginb_updater_github_token'));
        $this->assertSame(1, count($this->world->http->requests()));

        $this->world->admin->withInput('pluginb_updater_token', 'token-for-b-only');
        $this->world->http->queue($this->listing('plugin-b'));
        $this->post('save', 'pluginb');

        $this->assertSame('token-for-b-only', $this->world->options->get('pluginb_updater_github_token'));
        $this->assertSame('token-for-a-only', $this->world->options->get('fdcrm_updater_github_token'));
        $this->assertSame([['fdcrm_updater_notice', Notice::SAVED], ['pluginb_updater_notice', Notice::SAVED]], $this->redirects());
        $this->assertStringContainsString('name="pluginb_updater_token"', $this->section($pluginB));
        $this->assertStringContainsString('name="fdcrm_updater_token"', $this->section($this->plugin));
    }
}
