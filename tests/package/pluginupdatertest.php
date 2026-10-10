<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Package;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Internal\Http\HttpResponse;
use ScottOffen\WpGithubUpdater\Internal\Package\PluginUpdater;
use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;
use ScottOffen\WpGithubUpdater\Tests\Support\FakeError;
use ScottOffen\WpGithubUpdater\Tests\Support\FakePlatform;
use ScottOffen\WpGithubUpdater\Updater;

final class PluginUpdaterTest extends TestCase
{
    private const DIR = FakePlatform::PLUGIN_DIR . '/my-plugin';
    private const FILE = self::DIR . '/my-plugin.php';
    private const KEY = 'my-plugin/my-plugin.php';
    private const ASSET = 'https://api.github.com/repos/owner/repo/releases/assets/7';
    private const STORAGE = 'https://release-assets.githubusercontent.com/asset/7?sig=abc';
    private const HEADER = "<?php\n/**\n * Plugin Name: My Plugin\n * Plugin URI: https://example.com/my-plugin\n * Version: 0.9.0\n * Author: Scott Offen\n * Update URI: https://github.com/owner/repo\n */\n";

    private FakePlatform $world;

    private PluginUpdater $plugin;

    protected function setUp(): void
    {
        $this->world = new FakePlatform();
        $this->world->files->put(self::FILE, self::HEADER);
        $this->plugin = $this->build();
    }

    private function build(string $file = self::FILE, string $prefix = 'myplugin'): PluginUpdater
    {
        return Updater::plugin($file, $this->world->platform())->fromGitHub('owner/repo')->prefix($prefix)->register();
    }

    /**
     * Returns a 200 response with a list of one release that has the tag, the
     * notes, and one asset.
     *
     * @return HttpResponse The response.
     */
    private function listing(string $notes = "## What's Changed\n* A change", string $tag = 'v1.0.0'): HttpResponse
    {
        $slug = 'my-plugin';

        return new HttpResponse(200, ['ETag' => 'W/"e1"'], (string) json_encode([[
            'tag_name' => $tag,
            'draft' => false,
            'prerelease' => false,
            'body' => $notes,
            'published_at' => '2026-10-01T12:00:00Z',
            'html_url' => 'https://github.com/owner/repo/releases/tag/' . $tag,
            'assets' => [[
                'id' => 7,
                'name' => $slug . '-' . ltrim($tag, 'v') . '.zip',
                'url' => self::ASSET,
                'size' => 16,
                'digest' => '',
            ]],
        ]]));
    }

    /**
     * Queues a listing with the notes, runs the update filter, and returns the
     * result of the filter.
     *
     * @return mixed The result of the `update()` method.
     */
    private function prime(string $notes = "## What's Changed\n* A change"): mixed
    {
        $this->world->http->queue($this->listing($notes));

        return $this->update();
    }

    private function update(string $file = self::KEY, mixed $incoming = false): mixed
    {
        return $this->world->hooks->applyFilters('update_plugins_github.com', $incoming, [], $file, []);
    }

    private function popup(string $slug = 'my-plugin', string $action = 'plugin_information', mixed $incoming = false): mixed
    {
        return $this->world->hooks->applyFilters('plugins_api', $incoming, $action, (object) ['slug' => $slug]);
    }

    /** @param array<string, mixed>|mixed $extra */
    private function download(mixed $extra = ['plugin' => self::KEY, 'type' => 'plugin', 'action' => 'update'], mixed $reply = false, string $package = 'https://api.github.com/repos/owner/repo/releases/assets/1'): mixed
    {
        return $this->world->hooks->applyFilters('upgrader_pre_download', $reply, $package, null, $extra);
    }

    private function unpacked(string $source, mixed $extra = ['plugin' => self::KEY, 'type' => 'plugin', 'action' => 'update']): mixed
    {
        return $this->world->hooks->applyFilters('upgrader_source_selection', $source, '/tmp/upgrade/', null, $extra);
    }

    public function testTheContractReportsTheIdentityOfThePlugin(): void
    {
        $this->assertSame('my-plugin', $this->plugin->slug());
        $this->assertSame('owner/repo', $this->plugin->repository());
        $this->assertSame('MYPLUGIN_GITHUB_TOKEN', $this->plugin->tokenConstant());
        $this->assertSame('My Plugin', $this->plugin->name());
        $this->assertSame('0.9.0', $this->plugin->installedVersion());
    }

    public function testTheTokenStoreIsTheOneThatTheUpdateFlowUses(): void
    {
        $this->plugin->tokens()->save('a-saved-token-value');
        $this->world->http->queue($this->listing());

        $this->update();

        $this->assertSame('Bearer a-saved-token-value', $this->world->http->requests()[0]['headers']['Authorization']);
    }

    public function testTheNameIsTheSlugAndTheVersionIsEmptyWhenTheHeadersCannotBeRead(): void
    {
        $this->world->files->delete(self::FILE);

        $this->assertSame('my-plugin', $this->plugin->name());
        $this->assertSame('', $this->plugin->installedVersion());

        $this->world->files->put(self::FILE, "<?php\n// nothing useful here\n");

        $this->assertSame('my-plugin', $this->plugin->name());
        $this->assertSame('', $this->plugin->installedVersion());
    }

    public function testTheInstalledVersionIsReadFreshEachTime(): void
    {
        $this->assertSame('0.9.0', $this->plugin->installedVersion());

        $this->world->files->put(self::FILE, str_replace('0.9.0', '1.0.0', self::HEADER));

        $this->assertSame('1.0.0', $this->plugin->installedVersion());
    }

    public function testTheHooksAreRegisteredWithTheirPriorities(): void
    {
        $expected = [
            'update_plugins_github.com' => 99,
            'plugins_api' => 99,
            'upgrader_pre_download' => 1,
            'upgrader_source_selection' => 99,
        ];

        foreach ($expected as $hook => $priority) {
            $entries = $this->world->hooks->registered($hook);

            $this->assertCount(1, $entries, $hook);
            $this->assertSame('filter', $entries[0]['type'], $hook);
            $this->assertSame($priority, $entries[0]['priority'], $hook);
        }
    }

    public function testNoSwitchThemeHandlerIsRegistered(): void
    {
        $this->assertSame([], $this->world->hooks->registered('switch_theme'));
    }

    public function testAnotherPluginsUpdateIsPassedThroughUntouchedWithNoRequest(): void
    {
        $incoming = ['slug' => 'other', 'version' => '9.9.9', 'package' => 'https://example.com/other.zip'];

        $this->assertSame($incoming, $this->update('other-plugin/other-plugin.php', $incoming));
        $this->assertFalse($this->update('other-plugin/other-plugin.php', false));
        $this->assertSame([], $this->world->http->requests());
    }

    public function testTheUpdateForThisPluginHasTheSlugTheVersionAndThePackage(): void
    {
        $update = $this->prime();

        $this->assertSame(['slug' => 'my-plugin', 'version' => '1.0.0', 'package' => self::ASSET], $update);
    }

    public function testTheUpdateNeverNamesWhereTheReleasesAre(): void
    {
        $update = $this->prime();

        $this->assertIsArray($update);
        $this->assertArrayNotHasKey('url', $update);
        $this->assertStringNotContainsString('github.com/owner', (string) json_encode($update));
        $this->assertStringNotContainsString('releases/tag', (string) json_encode($update));
    }

    public function testTheUpdateTakesPrecedenceOverTheValueOfAnotherUpdater(): void
    {
        $this->world->http->queue($this->listing());
        $careless = ['slug' => 'my-plugin', 'version' => '99.0.0', 'package' => 'https://evil.example/x.zip'];

        $update = $this->update(self::KEY, $careless);

        $this->assertSame('1.0.0', $update['version']);
        $this->assertSame(self::ASSET, $update['package']);
    }

    public function testWithNoReleaseTheResultIsFalseEvenWhenAnotherUpdaterReturnedAValue(): void
    {
        $this->world->http->queue(new HttpResponse(404));
        $careless = ['slug' => 'my-plugin', 'version' => '99.0.0', 'package' => 'https://evil.example/x.zip'];

        $this->assertFalse($this->update(self::KEY, $careless));
    }

    public function testTheUpdateIsReturnedFromTheCacheWhileItIsFresh(): void
    {
        $this->prime();

        $this->update();
        $this->update();

        $this->assertCount(1, $this->world->http->requests());
    }

    public function testTheDetailsPopupHasEverythingItRequires(): void
    {
        $this->prime();

        $info = $this->popup();

        $this->assertIsObject($info);
        $this->assertSame('My Plugin', $info->name);
        $this->assertSame('my-plugin', $info->slug);
        $this->assertSame('1.0.0', $info->version);
        $this->assertSame('Scott Offen', $info->author);
        $this->assertSame('https://example.com/my-plugin', $info->homepage);
        $this->assertSame('2026-10-01T12:00:00Z', $info->last_updated);
        $this->assertSame(self::ASSET, $info->download_link);
        $this->assertTrue($info->external);
        $this->assertSame(['changelog'], array_keys($info->sections));
        $this->assertStringContainsString("<h4>What's Changed</h4>", $info->sections['changelog']);
    }

    public function testTheDetailsPopupNeverNamesWhereTheReleasesAre(): void
    {
        $this->prime();

        $info = (array) $this->popup();
        unset($info['download_link']);

        $this->assertStringNotContainsString('github.com/owner', (string) json_encode($info));
        $this->assertStringNotContainsString('releases/tag', (string) json_encode($info));
    }

    public function testTheDetailsPopupMakesNoRequestEvenWhenTheCacheHasExpired(): void
    {
        $this->prime();
        $this->world->clock->advance(13 * 3600);

        $info = $this->popup();

        $this->assertIsObject($info);
        $this->assertSame('1.0.0', $info->version);
        $this->assertCount(1, $this->world->http->requests());
    }

    public function testTheDetailsPopupGoesThroughTheSanitizer(): void
    {
        $this->prime();
        $this->world->sanitizer->using(static fn (string $html): string => '<p>SANITIZED</p>');

        $info = $this->popup();

        $this->assertSame('<p>SANITIZED</p>', $info->sections['changelog']);
    }

    public function testTheDetailsPopupShowsTheSentenceWhenTheReleaseHasNoNotes(): void
    {
        $this->prime('');

        $info = $this->popup();

        $this->assertSame('<p>No notes were published.</p>', $info->sections['changelog']);
    }

    public function testTheDetailsPopupLeavesOutWhatTheHeadersDoNotContain(): void
    {
        $this->world->files->put(self::FILE, "<?php\n// no headers\n");
        $this->prime();

        $info = $this->popup();

        $this->assertSame('my-plugin', $info->name);
        $this->assertSame('', $info->author);
        $this->assertSame('', $info->homepage);
    }

    public function testTheDetailsPopupIsAnErrorWhenNoReleaseIsStoredAndMakesNoRequest(): void
    {
        $info = $this->popup();

        $this->assertInstanceOf(FakeError::class, $info);
        $this->assertSame(Outcome::NO_RELEASE, $info->outcome);
        $this->assertSame([], $this->world->http->requests());
    }

    public function testTheDetailsPopupIsLeftAloneForAnythingElse(): void
    {
        $this->prime();
        $incoming = (object) ['name' => 'someone else'];

        $this->assertSame($incoming, $this->popup('other-plugin', 'plugin_information', $incoming));
        $this->assertSame($incoming, $this->popup('my-plugin', 'query_plugins', $incoming));
        $this->assertSame($incoming, $this->popup('my-plugin', 'hot_tags', $incoming));
        $this->assertFalse($this->world->hooks->applyFilters('plugins_api', false, 'plugin_information', ['slug' => 'my-plugin']));
        $this->assertFalse($this->world->hooks->applyFilters('plugins_api', false, 'plugin_information', null));
        $this->assertFalse($this->world->hooks->applyFilters('plugins_api', false, 'plugin_information', (object) []));
    }

    public function testADownloadForAnotherPluginIsPassedThrough(): void
    {
        $this->prime();

        $others = [
            ['plugin' => 'other-plugin/other-plugin.php', 'type' => 'plugin', 'action' => 'update'],
            ['theme' => 'my-plugin', 'type' => 'theme', 'action' => 'update'],
            ['type' => 'plugin', 'action' => 'install'],
            [],
            null,
            'my-plugin/my-plugin.php',
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

    public function testTheReleaseInTheStateIsDownloadedWhateverAddressWordPressHasStored(): void
    {
        $this->constants()->define('MYPLUGIN_GITHUB_TOKEN', 'secret-token');
        $this->prime();
        $this->world->http->queue(new HttpResponse(302, ['Location' => self::STORAGE]), new HttpResponse(200, [], 'THE ZIP'));

        $path = $this->download(package: 'https://api.github.com/repos/owner/repo/releases/assets/1');

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
            [new HttpResponse(429), Outcome::RATE_LIMITED],
            [HttpResponse::failure('timed out'), Outcome::NETWORK],
            [new HttpResponse(500), Outcome::BAD_RESPONSE],
        ];

        foreach ($cases as [$response, $outcome]) {
            $this->setUp();
            $this->prime();
            $this->world->http->queue($response);

            $result = $this->download();

            $this->assertInstanceOf(FakeError::class, $result, $outcome);
            $this->assertSame($outcome, $result->outcome, $outcome);
        }
    }

    public function testAFileThatDoesNotMatchItsDigestBecomesADigestError(): void
    {
        $body = (string) json_encode([[
            'tag_name' => 'v1.0.0', 'draft' => false, 'prerelease' => false, 'body' => '',
            'assets' => [['id' => 7, 'name' => 'my-plugin-1.0.0.zip', 'url' => self::ASSET, 'size' => 3, 'digest' => 'sha256:' . hash('sha256', 'the real bytes')]],
        ]]);
        $this->world->http->queue(new HttpResponse(200, ['ETag' => 'W/"x"'], $body));
        $this->update();
        $this->world->http->queue(new HttpResponse(200, [], 'tampered bytes'));

        $result = $this->download();

        $this->assertInstanceOf(FakeError::class, $result);
        $this->assertSame(Outcome::DIGEST_MISMATCH, $result->outcome);
    }

    public function testAnUnpackedFolderNamedAfterTheSlugIsAccepted(): void
    {
        foreach (['/tmp/upgrade/my-plugin/', '/tmp/upgrade/my-plugin', 'C:\\tmp\\upgrade\\my-plugin\\'] as $source) {
            $this->assertSame($source, $this->unpacked($source), $source);
        }
    }

    public function testAnUnpackedFolderWithAnotherNameIsRejected(): void
    {
        foreach (['/tmp/upgrade/my-plugin-1.0.0/', '/tmp/upgrade/other/', '/tmp/upgrade/', '/tmp/upgrade/My-Plugin/', '/tmp/upgrade/my-plugin/extra/'] as $source) {
            $result = $this->unpacked($source);

            $this->assertInstanceOf(FakeError::class, $result, $source);
            $this->assertSame(Outcome::FOLDER_MISMATCH, $result->outcome, $source);
        }
    }

    public function testTheFolderOfAnotherPluginOrAnInstallIsNotChecked(): void
    {
        $wrong = '/tmp/upgrade/whatever/';

        $this->assertSame($wrong, $this->unpacked($wrong, ['plugin' => 'other/other.php', 'type' => 'plugin', 'action' => 'update']));
        $this->assertSame($wrong, $this->unpacked($wrong, ['type' => 'plugin', 'action' => 'install']));
        $this->assertSame($wrong, $this->unpacked($wrong, ['theme' => 'my-plugin', 'type' => 'theme']));
        $this->assertSame($wrong, $this->unpacked($wrong, null));
    }

    public function testAnEarlierFailureIsPassedThroughUntouched(): void
    {
        $failure = new FakeError('earlier');

        $this->assertSame($failure, $this->world->hooks->applyFilters('upgrader_source_selection', $failure, '/tmp/upgrade/', null, ['plugin' => self::KEY]));
    }

    public function testCheckNowReturnsTheStateAndDiscardsTheStoredUpdateData(): void
    {
        $this->world->http->queue($this->listing());

        $state = $this->plugin->checkNow();

        $this->assertSame(Outcome::OK, $state->outcome);
        $this->assertSame('1.0.0', $state->release?->version);
        $this->assertSame(1, $this->world->updateData->forgotten());
        $this->assertSame($state->toArray(), $this->plugin->state()->toArray());
    }

    public function testCheckNowDiscardsTheStoredUpdateDataEvenWhenTheCheckFails(): void
    {
        $this->world->http->queue(HttpResponse::failure('timed out'));

        $state = $this->plugin->checkNow();

        $this->assertSame(Outcome::NETWORK, $state->outcome);
        $this->assertSame(1, $this->world->updateData->forgotten());
    }

    public function testTheStateReadsWithoutMakingARequest(): void
    {
        $this->assertFalse($this->plugin->state()->hasChecked());
        $this->assertSame([], $this->world->http->requests());
    }

    public function testTwoPluginsFromOneRepositoryNeverStepOnEachOther(): void
    {
        $fileB = FakePlatform::PLUGIN_DIR . '/plugin-b/plugin-b.php';
        $this->world->files->put($fileB, "<?php\n/* Plugin Name: Plugin B\n * Version: 2.0.0 */\n");
        $pluginB = $this->build($fileB, 'pluginb');

        $this->world->http->queue($this->listing());
        $a = $this->update('my-plugin/my-plugin.php');

        $this->assertIsArray($a);
        $this->assertSame('my-plugin', $a['slug']);
        $this->assertSame(1, count($this->world->http->requests()));

        $this->world->http->queue(new HttpResponse(200, ['ETag' => 'W/"b"'], (string) json_encode([[
            'tag_name' => 'v2.1.0', 'draft' => false, 'prerelease' => false, 'body' => '',
            'assets' => [['id' => 9, 'name' => 'plugin-b-2.1.0.zip', 'url' => 'https://api.github.com/repos/owner/repo/releases/assets/9', 'size' => 1, 'digest' => '']],
        ]])));
        $b = $this->update('plugin-b/plugin-b.php');

        $this->assertIsArray($b);
        $this->assertSame('plugin-b', $b['slug']);
        $this->assertSame('2.1.0', $b['version']);

        $this->assertSame('my-plugin', $this->plugin->slug());
        $this->assertSame('plugin-b', $pluginB->slug());
        $this->assertSame('1.0.0', $this->plugin->state()->release?->version);
        $this->assertSame('2.1.0', $pluginB->state()->release?->version);
        $this->assertSame('MYPLUGIN_GITHUB_TOKEN', $this->plugin->tokenConstant());
        $this->assertSame('PLUGINB_GITHUB_TOKEN', $pluginB->tokenConstant());

        $this->assertFalse($this->popup('plugin-c'));
        $this->assertSame('plugin-b', $this->popup('plugin-b')->slug);
        $this->assertSame('my-plugin', $this->popup('my-plugin')->slug);
    }

    public function testADownloadForOnePluginIsNotTakenByTheOtherWhenTheyShareARepository(): void
    {
        $fileB = FakePlatform::PLUGIN_DIR . '/plugin-b/plugin-b.php';
        $this->world->files->put($fileB, "<?php\n/* Plugin Name: Plugin B */\n");
        $this->build($fileB, 'pluginb');
        $this->prime();
        $this->world->http->queue(new HttpResponse(200, [], 'ZIP OF A'));

        $path = $this->download(['plugin' => self::KEY]);

        $this->assertIsString($path);
        $this->assertSame('ZIP OF A', $this->world->files->contents($path));
        $this->assertCount(2, $this->world->http->requests());
    }

    private function constants(): \ScottOffen\WpGithubUpdater\Tests\Support\FakeConstants
    {
        return $this->world->constants;
    }

    /**
     * Runs the `admin_head` action and returns what it printed.
     *
     * @return string The output.
     */
    private function head(): string
    {
        ob_start();
        $this->world->hooks->doAction('admin_head');

        return (string) ob_get_clean();
    }

    public function testThePopupStylesAreHookedOnTheHeadAsAnAction(): void
    {
        $entries = $this->world->hooks->registered('admin_head');

        $this->assertCount(1, $entries);
        $this->assertSame('action', $entries[0]['type']);
        $this->assertSame(10, $entries[0]['priority']);
        $this->assertSame(0, $entries[0]['accepted']);
    }

    public function testNothingIsPrintedInTheHeadBeforeThePopupWasHandled(): void
    {
        $this->prime();

        $this->assertSame('', $this->head());
    }

    public function testTheStylesArePrintedInTheHeadAfterThePopupOfThisPluginWasHandled(): void
    {
        $this->prime();
        $this->popup();

        $head = $this->head();

        $this->assertStringStartsWith('<style id="wp-github-updater-popup">', $head);
        $this->assertStringEndsWith('</style>', $head);
        $this->assertStringContainsString('#section-changelog .markdown-alert-caution{border-left-color:#cf222e}', $head);
    }

    public function testTheStylesAreNotPrintedWhenThePopupWasForAnotherPlugin(): void
    {
        $this->prime();
        $this->popup('another-plugin');

        $this->assertSame('', $this->head());
    }

    public function testTheStylesAreNotPrintedWhenTheRequestWasForAnotherAction(): void
    {
        $this->prime();
        $this->popup('my-plugin', 'query_plugins');

        $this->assertSame('', $this->head());
    }

    public function testTheStylesAreNotPrintedWhenThereWasNothingToShow(): void
    {
        $this->popup();

        $this->assertSame('', $this->head());
    }

    public function testTheStylesAreNotPrintedForAPopupThatWasNeverRequested(): void
    {
        $this->prime();
        $this->update();

        $this->assertSame('', $this->head());
    }
}
