<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Settings;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Internal\Release\Outcome;
use ScottOffen\WpGithubUpdater\Internal\Release\Release;
use ScottOffen\WpGithubUpdater\Internal\Settings\Notice;
use ScottOffen\WpGithubUpdater\Internal\Settings\SettingsForm;
use ScottOffen\WpGithubUpdater\Internal\Settings\SettingsModel;
use ScottOffen\WpGithubUpdater\Internal\Settings\SettingsView;
use ScottOffen\WpGithubUpdater\Internal\Update\ReleaseState;
use ScottOffen\WpGithubUpdater\Tests\Support\FakeSettingsText;

final class SettingsViewTest extends TestCase
{
    private function release(string $version = '1.1.0'): Release
    {
        return new Release('v' . $version, $version, '', '', '', 1, 'p-' . $version . '.zip', 'https://api.github.com/repos/o/r/releases/assets/1', 1, '');
    }

    /**
     * @param array<string, mixed> $changes Constructor arguments to replace.
     */
    private function model(array $changes = []): SettingsModel
    {
        $arguments = array_merge([
            'heading' => '',
            'state' => new ReleaseState(1_800_000_000, Outcome::OK, 200, 'W/"e"', $this->release()),
            'installedVersion' => '1.0.0',
            'whenChecked' => 'Oct 11',
            'locked' => false,
            'constantName' => 'FDCRM_GITHUB_TOKEN',
            'maskedToken' => '',
            'notice' => '',
            'tokenField' => 'fdcrm_updater_token',
            'saveForm' => new SettingsForm('https://x.test/save', '<input type="hidden" name="n" value="save-nonce">'),
            'removeForm' => new SettingsForm('https://x.test/remove', '<input type="hidden" name="n" value="remove-nonce">'),
            'checkForm' => new SettingsForm('https://x.test/check', '<input type="hidden" name="n" value="check-nonce">'),
        ], $changes);

        return new SettingsModel(...$arguments);
    }

    private function render(array $changes = []): string
    {
        return (new SettingsView(new FakeSettingsText()))->render($this->model($changes));
    }

    public function testTheSectionHasATableWithATokenRowAndAStatusRow(): void
    {
        $html = $this->render();

        $this->assertStringStartsWith('<div class="wp-github-updater">', $html);
        $this->assertStringEndsWith('</table></div>', $html);
        $this->assertStringContainsString('<table class="form-table" role="presentation">', $html);
        $this->assertStringContainsString('<label for="fdcrm_updater_token">Token label</label>', $html);
        $this->assertStringContainsString('<th scope="row">Status label</th>', $html);
    }

    public function testTheHeadingIsShownEscapedOnlyWhenThereIsOne(): void
    {
        $this->assertStringNotContainsString('<h2>', $this->render());
        $this->assertStringContainsString('<h2>Plugin Updates</h2>', $this->render(['heading' => 'Plugin Updates']));
        $this->assertStringContainsString('<h2>A &amp; &lt;b&gt;B&lt;/b&gt;</h2>', $this->render(['heading' => 'A & <b>B</b>']));
    }

    public function testTheTokenFieldIsAPasswordFieldThatNeverHasAValue(): void
    {
        $html = $this->render(['maskedToken' => "\u{2022}\u{2022}1234"]);

        $this->assertMatchesRegularExpression('/<input type="password" id="fdcrm_updater_token" name="fdcrm_updater_token" class="regular-text" autocomplete="new-password" placeholder="[^"]*" aria-describedby="fdcrm_updater_token-help">/u', $html);
        $this->assertSame(0, preg_match('/<input type="password"[^>]*\svalue=/', $html));
    }

    public function testASavedTokenShowsOnlyAsAMaskedPlaceholder(): void
    {
        $html = $this->render(['maskedToken' => "\u{2022}\u{2022}\u{2022}\u{2022}1234"]);

        $this->assertStringContainsString('placeholder="' . "\u{2022}\u{2022}\u{2022}\u{2022}" . '1234"', $html);
    }

    public function testWithNoSavedTokenThereIsNoPlaceholderAndNoRemoveForm(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('placeholder=', $html);
        $this->assertStringNotContainsString('Remove button', $html);
        $this->assertStringNotContainsString('remove-nonce', $html);
        $this->assertStringNotContainsString('https://x.test/remove', $html);
    }

    public function testASavedTokenAddsARemoveForm(): void
    {
        $html = $this->render(['maskedToken' => 'xx1234']);

        $this->assertStringContainsString('<form method="post" action="https://x.test/remove"><input type="hidden" name="n" value="remove-nonce"><button type="submit" class="button">Remove button</button></form>', $html);
    }

    public function testEachFormHasADistinctActionAndDistinctHiddenFields(): void
    {
        $html = $this->render(['maskedToken' => 'xx1234']);

        $this->assertStringContainsString('<form method="post" action="https://x.test/save"><input type="hidden" name="n" value="save-nonce"><input type="password"', $html);
        $this->assertStringContainsString('<form method="post" action="https://x.test/check"><input type="hidden" name="n" value="check-nonce"><button type="submit" class="button">Check button</button></form>', $html);
        $this->assertSame(1, substr_count($html, 'save-nonce'));
        $this->assertSame(1, substr_count($html, 'remove-nonce'));
        $this->assertSame(1, substr_count($html, 'check-nonce'));
    }

    public function testTheSaveButtonIsThePrimaryButton(): void
    {
        $this->assertStringContainsString('<button type="submit" class="button button-primary">Save button</button>', $this->render());
    }

    public function testTheFormActionsAreEscaped(): void
    {
        $html = $this->render(['saveForm' => new SettingsForm('https://x.test/save?a=1&b="2"', '')]);

        $this->assertStringContainsString('action="https://x.test/save?a=1&amp;b=&quot;2&quot;"', $html);
    }

    public function testTheHelpIsLinkedToTheField(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('aria-describedby="fdcrm_updater_token-help"', $html);
        $this->assertStringContainsString('<p class="description" id="fdcrm_updater_token-help">Token help</p>', $html);
    }

    public function testAConstantLocksTheFieldAndShowsItsNameInsteadOfAToken(): void
    {
        $html = $this->render(['locked' => true, 'maskedToken' => '']);

        $this->assertStringContainsString('<input type="text" id="fdcrm_updater_token" class="regular-text" value="FDCRM_GITHUB_TOKEN" disabled readonly aria-describedby="fdcrm_updater_token-help">', $html);
        $this->assertStringContainsString('<p class="description" id="fdcrm_updater_token-help">Set by FDCRM_GITHUB_TOKEN</p>', $html);
        $this->assertStringNotContainsString('type="password"', $html);
        $this->assertStringNotContainsString('Save button', $html);
        $this->assertStringNotContainsString('Remove button', $html);
        $this->assertStringNotContainsString('save-nonce', $html);
        $this->assertStringNotContainsString('remove-nonce', $html);
        $this->assertStringNotContainsString('Token help', $html);
    }

    public function testALockedSectionStillCanCheckForUpdates(): void
    {
        $html = $this->render(['locked' => true]);

        $this->assertStringContainsString('Check button', $html);
        $this->assertStringContainsString('check-nonce', $html);
    }

    public function testALockedSectionNeverShowsAMaskedToken(): void
    {
        $html = $this->render(['locked' => true, 'maskedToken' => 'leaky1234']);

        $this->assertStringNotContainsString('leaky1234', $html);
    }

    public function testBeforeAnyCheckTheStatusReportsNoCheckAndShowsTheInstalledVersion(): void
    {
        $html = $this->render(['state' => ReleaseState::empty(), 'whenChecked' => '']);

        $this->assertStringContainsString('<p>Never checked Installed 1.0.0</p>', $html);
        $this->assertStringNotContainsString('Last checked', $html);
        $this->assertStringNotContainsString('Latest', $html);
        $this->assertStringNotContainsString('class="wp-github-updater-problem"', $html);
    }

    public function testAfterASuccessfulCheckTheStatusShowsTheTimeTheInstalledAndTheLatestVersion(): void
    {
        $this->assertStringContainsString('<p>Last checked Oct 11 Installed 1.0.0 Latest 1.1.0</p>', $this->render());
    }

    public function testAnUnknownInstalledVersionIsLeftOut(): void
    {
        $html = $this->render(['installedVersion' => '']);

        $this->assertStringContainsString('<p>Last checked Oct 11 Latest 1.1.0</p>', $html);
        $this->assertStringNotContainsString('Installed', $html);
    }

    public function testAFailedCheckWithAnUnchangedReleaseShowsTheVersionAndTheProblem(): void
    {
        $state = new ReleaseState(1_800_000_000, Outcome::NETWORK, 0, 'W/"e"', $this->release());

        $this->assertStringContainsString(
            '<p>Last checked Oct 11 Installed 1.0.0 Latest 1.1.0 <span class="wp-github-updater-problem">Problem network</span></p>',
            $this->render(['state' => $state]),
        );
    }

    public function testAFailedCheckWithNoReleaseShowsOnlyTheProblem(): void
    {
        foreach ([Outcome::AUTH, Outcome::NOT_FOUND, Outcome::RATE_LIMITED, Outcome::BAD_RESPONSE, Outcome::ASSET_MISMATCH, 'a_future_code'] as $outcome) {
            $html = $this->render(['state' => new ReleaseState(1_800_000_000, $outcome, 401)]);

            $this->assertStringContainsString('<p>Last checked Oct 11 Installed 1.0.0 <span class="wp-github-updater-problem">Problem ' . $outcome . '</span></p>', $html, $outcome);
            $this->assertStringNotContainsString('Latest', $html, $outcome);
            $this->assertStringNotContainsString('None found', $html, $outcome);
        }
    }

    public function testNoStableReleaseShowsNoReleaseFoundAndNothingElse(): void
    {
        $html = $this->render(['state' => new ReleaseState(1_800_000_000, Outcome::NO_RELEASE, 200)]);

        $this->assertStringContainsString('<p>Last checked Oct 11 Installed 1.0.0 None found</p>', $html);
        $this->assertStringNotContainsString('wp-github-updater-problem', $html);
    }

    public function testEveryValueInTheStatusIsEscaped(): void
    {
        $state = new ReleaseState(1_800_000_000, '<b>bad</b>', 0, '', $this->release('<i>1</i>'));
        $html = $this->render(['state' => $state, 'installedVersion' => '<u>2</u>', 'whenChecked' => '<s>now</s>']);

        $this->assertStringNotContainsString('<i>1', $html);
        $this->assertStringNotContainsString('<u>2', $html);
        $this->assertStringNotContainsString('<s>now', $html);
        $this->assertStringNotContainsString('<b>bad', $html);
        $this->assertStringContainsString('&lt;i&gt;1&lt;/i&gt;', $html);
        $this->assertStringContainsString('&lt;u&gt;2&lt;/u&gt;', $html);
        $this->assertStringContainsString('&lt;s&gt;now&lt;/s&gt;', $html);
        $this->assertStringContainsString('Problem &lt;b&gt;bad&lt;/b&gt;', $html);
    }

    public function testTheConstantNameIsEscaped(): void
    {
        $html = $this->render(['locked' => true, 'constantName' => 'A"><script>x</script>']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('value="A&quot;&gt;&lt;script&gt;x&lt;/script&gt;"', $html);
    }

    public function testEveryKnownNoticeIsShownWithItsClassAndText(): void
    {
        foreach (Notice::all() as $code) {
            $html = $this->render(['notice' => $code]);
            $class = Notice::isSuccess($code) ? 'notice-success' : 'notice-error';

            $this->assertStringContainsString('<div class="notice ' . $class . ' is-dismissible"><p>Notice ' . $code . '</p></div>', $html, $code);
        }
    }

    public function testASuccessNoticeIsNeverStyledAsAnError(): void
    {
        $this->assertStringNotContainsString('notice-error', $this->render(['notice' => Notice::SAVED]));
        $this->assertStringNotContainsString('notice-success', $this->render(['notice' => Notice::EXPIRED]));
    }

    public function testNoNoticeIsShownForNothingOrForACodeThatIsNotOnTheList(): void
    {
        foreach (['', 'unknown', 'SAVED', '<script>alert(1)</script>', 'saved&x=1', 'saved '] as $code) {
            $html = $this->render(['notice' => $code]);

            $this->assertStringNotContainsString('class="notice', $html, var_export($code, true));
            $this->assertStringNotContainsString('<script>alert', $html, var_export($code, true));
            $this->assertStringNotContainsString('Notice ', $html, var_export($code, true));
        }
    }

    public function testTheNoticeComesBeforeTheTable(): void
    {
        $html = $this->render(['heading' => 'Updates', 'notice' => Notice::CHECKED]);

        $this->assertLessThan(strpos($html, '<table'), strpos($html, 'class="notice'));
        $this->assertLessThan(strpos($html, 'class="notice'), strpos($html, '<h2>'));
    }

    public function testEveryTextFromTheSeamIsEscaped(): void
    {
        $view = new SettingsView(new FakeSettingsText('<b>&"'));
        $states = [
            ReleaseState::empty(),
            new ReleaseState(1_800_000_000, Outcome::OK, 200, 'W/"e"', $this->release()),
            new ReleaseState(1_800_000_000, Outcome::NETWORK, 0, 'W/"e"', $this->release()),
            new ReleaseState(1_800_000_000, Outcome::AUTH, 401),
            new ReleaseState(1_800_000_000, Outcome::NO_RELEASE, 200),
        ];

        foreach ($states as $state) {
            foreach ([false, true] as $locked) {
                $html = $view->render($this->model(['state' => $state, 'locked' => $locked, 'maskedToken' => $locked ? '' : 'xx1234', 'notice' => Notice::SAVED]));

                $this->assertStringNotContainsString('<b>', $html);
                $this->assertStringNotContainsString('&"', $html);
                $this->assertStringContainsString('&lt;b&gt;&amp;&quot;', $html);
            }
        }
    }

    public function testEveryOneOfTheTextsIsPartOfTheEscapedSections(): void
    {
        $html = (new SettingsView(new FakeSettingsText('<b>&"')))->render($this->model(['maskedToken' => 'xx1234', 'notice' => Notice::CHECKED]));

        foreach (['Token label', 'Token help', 'Save button', 'Remove button', 'Check button', 'Status label', 'Last checked Oct 11', 'Installed 1.0.0', 'Latest 1.1.0', 'Notice checked'] as $text) {
            $this->assertStringContainsString($text . '&lt;b&gt;&amp;&quot;', $html, $text);
        }
    }

    public function testAMaskedTokenWithMarkupInItIsEscapedInThePlaceholder(): void
    {
        $html = $this->render(['maskedToken' => "\u{2022}\u{2022}<\"&>"]);

        $this->assertStringContainsString('placeholder="' . "\u{2022}\u{2022}" . '&lt;&quot;&amp;&gt;"', $html);
        $this->assertStringNotContainsString('<"', $html);
        $this->assertSame(1, substr_count($html, 'placeholder='));
    }

    public function testTheNameOfTheFieldIsEscapedInTheLabelTheFieldAndTheHelp(): void
    {
        $html = $this->render(['tokenField' => 'a"b<c']);

        $this->assertStringContainsString('<label for="a&quot;b&lt;c">', $html);
        $this->assertStringContainsString('id="a&quot;b&lt;c" name="a&quot;b&lt;c"', $html);
        $this->assertStringContainsString('aria-describedby="a&quot;b&lt;c-help"', $html);
        $this->assertStringNotContainsString('a"b<c', $html);
    }
}
