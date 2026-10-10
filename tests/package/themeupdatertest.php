<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Package;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Http\HttpResponse;
use ScottOffen\WpGithubUpdater\Package\ThemeUpdater;
use ScottOffen\WpGithubUpdater\Release\Outcome;
use ScottOffen\WpGithubUpdater\Tests\Support\FakeError;
use ScottOffen\WpGithubUpdater\Tests\Support\FakePlatform;
use ScottOffen\WpGithubUpdater\Updater;

final class ThemeUpdaterTest extends TestCase
{
    private const THEMES = '/wp/wp-content/themes';
    private const DIR = self::THEMES . '/my-theme';
    private const FUNCTIONS = self::DIR . '/functions.php';
    private const STYLE = self::DIR . '/style.css';
    private const ASSET = 'https://api.github.com/repos/owner/repo/releases/assets/7';
    private const STORAGE = 'https://release-assets.githubusercontent.com/asset/7?sig=abc';
    private const NOTES_ACTION = 'mytheme_updater_notes';
    private const NOTES_URL = 'https://example.test/wp-admin/admin-post.php?action=mytheme_updater_notes';
    private const STYLE_HEADER = "/*\nTheme Name: My Theme\nTheme URI: https://example.com/my-theme\nAuthor: Scott Offen\nVersion: 0.9.0\nUpdate URI: https://github.com/owner/repo\n*/\n";

    private FakePlatform $world;

    private ThemeUpdater $theme;

    protected function setUp(): void
    {
        $this->world = new FakePlatform();
        $this->world->files->put(self::FUNCTIONS, "<?php\n// functions\n");
        $this->world->files->put(self::STYLE, self::STYLE_HEADER);
        $this->theme = $this->build();
    }

    private function build(string $file = self::FUNCTIONS, string $prefix = 'mytheme'): ThemeUpdater
    {
        $updater = Updater::theme($file, $this->world->platform())->fromGitHub('owner/repo')->prefix($prefix)->register();

        $this->assertInstanceOf(ThemeUpdater::class, $updater);

        return $updater;
    }

    private function listing(string $notes = "## What's Changed\n* A change", string $tag = 'v1.0.0', string $slug = 'my-theme', int $id = 7): HttpResponse
    {
        return new HttpResponse(200, ['ETag' => 'W/"e1"'], (string) json_encode([[
            'tag_name' => $tag,
            'draft' => false,
            'prerelease' => false,
            'body' => $notes,
            'published_at' => '2026-10-01T12:00:00Z',
            'html_url' => 'https://github.com/owner/repo/releases/tag/' . $tag,
            'assets' => [[
                'id' => $id,
                'name' => $slug . '-' . ltrim($tag, 'v') . '.zip',
                'url' => 'https://api.github.com/repos/owner/repo/releases/assets/' . $id,
                'size' => 16,
                'digest' => '',
            ]],
        ]]));
    }

    private function prime(string $notes = "## What's Changed\n* A change"): mixed
    {
        $this->world->http->queue($this->listing($notes));

        return $this->update();
    }

    private function update(string $stylesheet = 'my-theme', mixed $incoming = false): mixed
    {
        return $this->world->hooks->applyFilters('update_themes_github.com', $incoming, [], $stylesheet, []);
    }

    private function showNotes(string $action = self::NOTES_ACTION): void
    {
        $this->world->hooks->doAction('admin_post_' . $action);
    }

    /** @param array<string, mixed>|mixed $extra */
    private function download(mixed $extra = ['theme' => 'my-theme', 'type' => 'theme', 'action' => 'update'], mixed $reply = false): mixed
    {
        return $this->world->hooks->applyFilters('upgrader_pre_download', $reply, 'https://api.github.com/repos/owner/repo/releases/assets/1', null, $extra);
    }

    private function unpacked(string $source, mixed $extra = ['theme' => 'my-theme', 'type' => 'theme', 'action' => 'update']): mixed
    {
        return $this->world->hooks->applyFilters('upgrader_source_selection', $source, '/tmp/upgrade/', null, $extra);
    }

    public function testTheContractReportsTheIdentityOfTheTheme(): void
    {
        $this->assertSame('my-theme', $this->theme->slug());
        $this->assertSame('owner/repo', $this->theme->repository());
        $this->assertSame('MYTHEME_GITHUB_TOKEN', $this->theme->tokenConstant());
        $this->assertSame('My Theme', $this->theme->name());
        $this->assertSame('0.9.0', $this->theme->installedVersion());
        $this->assertSame('manage_options', $this->theme->capability());
    }

    public function testTheNameAndVersionComeFromTheStyleSheetNotFromFunctionsPhp(): void
    {
        $this->world->files->put(self::FUNCTIONS, "<?php\n/*\n * Plugin Name: Not This\n * Version: 9.9.9\n */\n");

        $this->assertSame('My Theme', $this->theme->name());
        $this->assertSame('0.9.0', $this->theme->installedVersion());
    }

    public function testTheNameIsTheSlugAndTheVersionIsEmptyWhenTheStyleSheetCannotBeRead(): void
    {
        $this->world->files->delete(self::STYLE);

        $this->assertSame('my-theme', $this->theme->name());
        $this->assertSame('', $this->theme->installedVersion());
    }

    public function testTheHooksAreRegisteredAndNoPluginHooksAre(): void
    {
        $update = $this->world->hooks->registered('update_themes_github.com');
        $notes = $this->world->hooks->registered('admin_post_mytheme_updater_notes');

        $this->assertCount(1, $update);
        $this->assertSame('filter', $update[0]['type']);
        $this->assertSame(99, $update[0]['priority']);
        $this->assertCount(1, $notes);
        $this->assertSame('action', $notes[0]['type']);
        $this->assertSame(10, $notes[0]['priority']);
        $this->assertSame(1, $this->world->hooks->registered('upgrader_pre_download')[0]['priority']);
        $this->assertSame(99, $this->world->hooks->registered('upgrader_source_selection')[0]['priority']);
        $this->assertSame([], $this->world->hooks->registered('update_plugins_github.com'));
        $this->assertSame([], $this->world->hooks->registered('plugins_api'));
    }

    public function testAnotherThemesUpdateIsPassedThroughUntouchedWithNoRequest(): void
    {
        $incoming = ['theme' => 'other', 'version' => '9.9.9', 'package' => 'https://example.com/other.zip', 'url' => 'https://example.com'];

        $this->assertSame($incoming, $this->update('other-theme', $incoming));
        $this->assertFalse($this->update('other-theme', false));
        $this->assertSame([], $this->world->http->requests());
    }

    public function testTheUpdateForThisThemeHasTheThemeTheVersionThePackageAndTheNotesAddress(): void
    {
        $update = $this->prime();

        $this->assertSame([
            'theme' => 'my-theme',
            'version' => '1.0.0',
            'package' => self::ASSET,
            'url' => self::NOTES_URL,
        ], $update);
    }

    public function testTheUpdateNeverNamesWhereTheReleasesAre(): void
    {
        $update = $this->prime();

        $this->assertIsArray($update);
        $this->assertStringNotContainsString('github.com/owner', $update['url']);
        $this->assertStringNotContainsString('owner/repo', $update['url']);
        $this->assertStringNotContainsString('releases/tag', (string) json_encode($update));
    }

    public function testTheUpdateWinsOverAnotherUpdatersAnswer(): void
    {
        $this->world->http->queue($this->listing());
        $careless = ['theme' => 'my-theme', 'version' => '99.0.0', 'package' => 'https://evil.example/x.zip', 'url' => 'https://evil.example'];

        $update = $this->update('my-theme', $careless);

        $this->assertSame('1.0.0', $update['version']);
        $this->assertSame(self::ASSET, $update['package']);
        $this->assertSame(self::NOTES_URL, $update['url']);
    }

    public function testWithNoReleaseTheAnswerIsFalseEvenWhenAnotherUpdaterAnswered(): void
    {
        $this->world->http->queue(new HttpResponse(404));
        $careless = ['theme' => 'my-theme', 'version' => '99.0.0', 'package' => 'https://evil.example/x.zip', 'url' => 'https://evil.example'];

        $this->assertFalse($this->update('my-theme', $careless));
    }

    public function testTheUpdateIsAnsweredFromTheCacheWhileItIsFresh(): void
    {
        $this->prime();

        $this->update();
        $this->update();

        $this->assertCount(1, $this->world->http->requests());
    }

    public function testThePluginFilterNeverAnswersForATheme(): void
    {
        $this->prime();

        $this->assertFalse($this->world->hooks->applyFilters('update_plugins_github.com', false, [], 'my-theme/functions.php', []));
        $this->assertSame([], $this->world->hooks->registered('update_plugins_github.com'));
    }

    public function testThePageShowsTheThemeNameTheVersionAndTheNotes(): void
    {
        $this->prime();
        $this->world->admin->allow('update_themes');

        $this->showNotes();

        $this->assertCount(1, $this->world->admin->responses());
        $html = $this->world->admin->responses()[0];

        $this->assertStringContainsString('<title>My Theme 1.0.0</title>', $html);
        $this->assertStringContainsString('<h1>My Theme 1.0.0</h1>', $html);
        $this->assertStringContainsString("<h6>What's Changed</h6>", $html);
        $this->assertSame(1, $this->world->admin->terminated());
        $this->assertSame(0, $this->world->admin->denied());
    }

    public function testThePageRefusesAPersonWhoCannotUpdateThemes(): void
    {
        $this->prime();
        $this->world->admin->allow('manage_options', 'edit_theme_options', 'read');

        $this->showNotes();

        $this->assertSame(1, $this->world->admin->denied());
        $this->assertSame([], $this->world->admin->responses());
        $this->assertSame(0, $this->world->admin->terminated());
    }

    public function testThePageRefusesEveryoneWhenNoOneIsSignedIn(): void
    {
        $this->prime();

        $this->showNotes();

        $this->assertSame(1, $this->world->admin->denied());
        $this->assertSame([], $this->world->admin->responses());
    }

    public function testThePageMakesNoRequestEvenWhenTheCacheHasExpired(): void
    {
        $this->prime();
        $this->world->admin->allow('update_themes');
        $this->world->clock->advance(13 * 3600);

        $this->showNotes();

        $this->assertCount(1, $this->world->admin->responses());
        $this->assertStringContainsString('My Theme 1.0.0', $this->world->admin->responses()[0]);
        $this->assertCount(1, $this->world->http->requests());
    }

    public function testThePageNotesGoThroughTheSanitizer(): void
    {
        $this->prime();
        $this->world->admin->allow('update_themes');
        $this->world->sanitizer->using(static fn (string $html): string => '<p>SANITIZED</p>');

        $this->showNotes();

        $this->assertStringContainsString("<h1>My Theme 1.0.0</h1>\n<p>SANITIZED</p>\n", $this->world->admin->responses()[0]);
    }

    public function testThePageShowsTheSentenceWhenTheReleaseHasNoNotes(): void
    {
        $this->prime('');
        $this->world->admin->allow('update_themes');

        $this->showNotes();

        $this->assertStringContainsString('<p>No notes were published.</p>', $this->world->admin->responses()[0]);
    }

    public function testThePageSaysSoWhenNoReleaseIsStoredAndMakesNoRequest(): void
    {
        $this->world->admin->allow('update_themes');

        $this->showNotes();

        $this->assertCount(1, $this->world->admin->responses());
        $html = $this->world->admin->responses()[0];

        $this->assertStringContainsString('<title>My Theme</title>', $html);
        $this->assertStringContainsString('<p>Message for ' . Outcome::NO_RELEASE . '.</p>', $html);
        $this->assertSame(1, $this->world->admin->terminated());
        $this->assertSame([], $this->world->http->requests());
    }

    public function testThePageEscapesTheNoReleaseSentence(): void
    {
        $this->world->messages->returning('Nothing <b>yet</b> & "more"');
        $this->world->admin->allow('update_themes');

        $this->showNotes();

        $html = $this->world->admin->responses()[0];

        $this->assertStringContainsString('<p>Nothing &lt;b&gt;yet&lt;/b&gt; &amp; &quot;more&quot;</p>', $html);
        $this->assertStringNotContainsString('<b>yet</b>', $html);
    }

    public function testThePageEscapesAThemeNameWithMarkup(): void
    {
        $this->world->files->put(self::STYLE, "/*\nTheme Name: Fish & <b>Chips</b>\nVersion: 0.9.0\n*/\n");
        $this->prime();
        $this->world->admin->allow('update_themes');

        $this->showNotes();

        $html = $this->world->admin->responses()[0];

        $this->assertStringContainsString('<h1>Fish &amp; &lt;b&gt;Chips&lt;/b&gt; 1.0.0</h1>', $html);
        $this->assertStringNotContainsString('<b>Chips', $html);
    }

    public function testThePageNeverNamesTheRepositoryOrTheReleasePage(): void
    {
        $this->prime();
        $this->world->admin->allow('update_themes');

        $this->showNotes();

        $html = $this->world->admin->responses()[0];

        $this->assertStringNotContainsString('owner/repo', $html);
        $this->assertStringNotContainsString('releases/tag', $html);
        $this->assertStringNotContainsString('api.github.com', $html);
    }

    public function testAnotherThemesHandlerIsNotThisOne(): void
    {
        $this->prime();
        $this->world->admin->allow('update_themes');

        $this->showNotes('othertheme_updater_notes');

        $this->assertSame([], $this->world->admin->responses());
    }

    public function testADownloadForAnotherPackageIsPassedThrough(): void
    {
        $this->prime();

        $others = [
            ['theme' => 'other-theme', 'type' => 'theme', 'action' => 'update'],
            ['plugin' => 'my-theme/my-theme.php', 'type' => 'plugin', 'action' => 'update'],
            ['type' => 'theme', 'action' => 'install'],
            [],
            null,
            'my-theme',
        ];

        foreach ($others as $extra) {
            $this->assertFalse($this->download($extra), var_export($extra, true));
        }

        $this->assertCount(1, $this->world->http->requests());
    }

    public function testADownloadSomethingElseAlreadyHandledIsLeftAlone(): void
    {
        $this->prime();

        $this->assertSame('/tmp/already-downloaded.zip', $this->download(reply: '/tmp/already-downloaded.zip'));
        $this->assertCount(1, $this->world->http->requests());
    }

    public function testTheReleaseInTheStateIsDownloadedWithTheTokenOnlyOnTheApi(): void
    {
        $this->world->constants->define('MYTHEME_GITHUB_TOKEN', 'secret-token');
        $this->prime();
        $this->world->http->queue(new HttpResponse(302, ['Location' => self::STORAGE]), new HttpResponse(200, [], 'THE ZIP'));

        $path = $this->download();

        $this->assertIsString($path);
        $this->assertSame('THE ZIP', $this->world->files->contents($path));

        $requests = $this->world->http->requests();

        $this->assertSame(self::ASSET, $requests[1]['url']);
        $this->assertSame('Bearer secret-token', $requests[1]['headers']['Authorization']);
        $this->assertSame(self::STORAGE, $requests[2]['url']);
        $this->assertArrayNotHasKey('Authorization', $requests[2]['headers']);
    }

    public function testADownloadWithNoReleaseStoredIsAnErrorAndMakesNoRequest(): void
    {
        $result = $this->download();

        $this->assertInstanceOf(FakeError::class, $result);
        $this->assertSame(Outcome::NO_RELEASE, $result->outcome);
        $this->assertSame([], $this->world->http->requests());
    }

    public function testADownloadThatFailsBecomesTheErrorForItsOutcome(): void
    {
        $cases = [
            [new HttpResponse(401), Outcome::AUTH],
            [new HttpResponse(404), Outcome::NOT_FOUND],
            [HttpResponse::failure('timed out'), Outcome::NETWORK],
        ];

        foreach ($cases as [$answer, $outcome]) {
            $this->setUp();
            $this->prime();
            $this->world->http->queue($answer);

            $result = $this->download();

            $this->assertInstanceOf(FakeError::class, $result, $outcome);
            $this->assertSame($outcome, $result->outcome, $outcome);
        }
    }

    public function testAnUnpackedFolderNamedAfterTheSlugIsAccepted(): void
    {
        foreach (['/tmp/upgrade/my-theme/', '/tmp/upgrade/my-theme', 'C:\\tmp\\upgrade\\my-theme\\'] as $source) {
            $this->assertSame($source, $this->unpacked($source), $source);
        }
    }

    public function testAnUnpackedFolderWithAnotherNameIsRejected(): void
    {
        foreach (['/tmp/upgrade/my-theme-1.0.0/', '/tmp/upgrade/other/', '/tmp/upgrade/', '/tmp/upgrade/My-Theme/'] as $source) {
            $result = $this->unpacked($source);

            $this->assertInstanceOf(FakeError::class, $result, $source);
            $this->assertSame(Outcome::FOLDER_MISMATCH, $result->outcome, $source);
        }
    }

    public function testTheFolderOfAnotherPackageOrAnInstallIsNotChecked(): void
    {
        $wrong = '/tmp/upgrade/whatever/';

        $this->assertSame($wrong, $this->unpacked($wrong, ['theme' => 'other-theme', 'type' => 'theme', 'action' => 'update']));
        $this->assertSame($wrong, $this->unpacked($wrong, ['type' => 'theme', 'action' => 'install']));
        $this->assertSame($wrong, $this->unpacked($wrong, ['plugin' => 'my-theme/my-theme.php', 'type' => 'plugin']));
        $this->assertSame($wrong, $this->unpacked($wrong, null));
    }

    public function testCheckNowReturnsTheStateAndMakesWordPressForgetItsThemeData(): void
    {
        $this->world->http->queue($this->listing());

        $state = $this->theme->checkNow();

        $this->assertSame(Outcome::OK, $state->outcome);
        $this->assertSame('1.0.0', $state->release?->version);
        $this->assertSame(1, $this->world->updateData->themesForgotten());
        $this->assertSame(0, $this->world->updateData->forgotten());
        $this->assertSame($state->toArray(), $this->theme->state()->toArray());
    }

    public function testTwoThemesFromOneRepositoryNeverStepOnEachOther(): void
    {
        $functionsB = self::THEMES . '/theme-b/functions.php';
        $this->world->files->put($functionsB, "<?php\n");
        $this->world->files->put(self::THEMES . '/theme-b/style.css', "/*\nTheme Name: Theme B\nVersion: 2.0.0\n*/\n");
        $themeB = $this->build($functionsB, 'themeb');

        $this->world->http->queue($this->listing());
        $a = $this->update('my-theme');

        $this->world->http->queue($this->listing('', 'v2.1.0', 'theme-b', 9));
        $b = $this->update('theme-b');

        $this->assertSame('1.0.0', $a['version']);
        $this->assertSame('2.1.0', $b['version']);
        $this->assertSame('theme-b', $b['theme']);
        $this->assertStringEndsWith('action=themeb_updater_notes', $b['url']);
        $this->assertStringEndsWith('action=mytheme_updater_notes', $a['url']);
        $this->assertSame('1.0.0', $this->theme->state()->release?->version);
        $this->assertSame('2.1.0', $themeB->state()->release?->version);
        $this->assertSame('THEMEB_GITHUB_TOKEN', $themeB->tokenConstant());
    }

    public function testAThemeAndAPluginOnOneSiteNeverStepOnEachOther(): void
    {
        $pluginFile = FakePlatform::PLUGIN_DIR . '/my-theme/my-theme.php';
        $this->world->files->put($pluginFile, "<?php\n/* Plugin Name: Same Slug */\n");
        $plugin = Updater::plugin($pluginFile, $this->world->platform())->fromGitHub('owner/repo')->prefix('myplugin')->register();

        $this->world->http->queue($this->listing());
        $themeUpdate = $this->update('my-theme');
        $this->world->http->queue($this->listing());
        $pluginUpdate = $this->world->hooks->applyFilters('update_plugins_github.com', false, [], 'my-theme/my-theme.php', []);

        $this->assertSame(['theme', 'version', 'package', 'url'], array_keys($themeUpdate));
        $this->assertSame(['slug', 'version', 'package'], array_keys($pluginUpdate));

        $this->assertSame('left alone', $this->update('my-theme/my-theme.php', 'left alone'));
        $this->assertSame('left alone', $this->world->hooks->applyFilters('update_plugins_github.com', 'left alone', [], 'my-theme', []));
        $this->assertCount(2, $this->world->http->requests());

        $this->assertTrue($this->world->options->has('mytheme_updater_release_state'));
        $this->assertTrue($this->world->options->has('myplugin_updater_release_state'));
        $this->assertSame('MYPLUGIN_GITHUB_TOKEN', $plugin->tokenConstant());
        $this->assertSame('MYTHEME_GITHUB_TOKEN', $this->theme->tokenConstant());
    }

    public function testAThemeDownloadAndAPluginDownloadAreEachHandledByOneUpdaterOnly(): void
    {
        $pluginFile = FakePlatform::PLUGIN_DIR . '/my-theme/my-theme.php';
        $this->world->files->put($pluginFile, "<?php\n/* Plugin Name: Same Slug */\n");
        Updater::plugin($pluginFile, $this->world->platform())->fromGitHub('owner/repo')->prefix('myplugin')->register();

        $this->world->http->queue($this->listing());
        $this->update('my-theme');
        $this->world->http->queue($this->listing());
        $this->world->hooks->applyFilters('update_plugins_github.com', false, [], 'my-theme/my-theme.php', []);
        $before = count($this->world->http->requests());

        $this->world->http->queue(new HttpResponse(200, [], 'THEME ZIP'));
        $themePath = $this->download(['theme' => 'my-theme']);

        $this->assertSame('THEME ZIP', $this->world->files->contents((string) $themePath));
        $this->assertSame($before + 1, count($this->world->http->requests()));

        $this->world->http->queue(new HttpResponse(200, [], 'PLUGIN ZIP'));
        $pluginPath = $this->download(['plugin' => 'my-theme/my-theme.php']);

        $this->assertSame('PLUGIN ZIP', $this->world->files->contents((string) $pluginPath));
        $this->assertSame($before + 2, count($this->world->http->requests()));
    }

    public function testItRegistersNothingOnTheHeadBecauseAThemeHasNoPopup(): void
    {
        $this->assertSame([], $this->world->hooks->registered('admin_head'));
    }
}
