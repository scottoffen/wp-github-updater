<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\WordPress;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Tests\Support\WpStub;
use ScottOffen\WpGithubUpdater\WordPress\WpAdmin;

final class WpAdminTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $post = [];

    /** @var array<string, mixed> */
    private array $get = [];

    /** @var array<string, mixed> */
    private array $request = [];

    protected function setUp(): void
    {
        WpStub::reset();
        $this->post = $_POST;
        $this->get = $_GET;
        $this->request = $_REQUEST;
        $_POST = [];
        $_GET = [];
        $_REQUEST = [];
    }

    protected function tearDown(): void
    {
        $_POST = $this->post;
        $_GET = $this->get;
        $_REQUEST = $this->request;
        WpStub::reset();
    }

    public function testThePostUrlIsTheAdminPostHandlerForTheAction(): void
    {
        $this->assertSame(
            'https://example.test/wp-admin/admin-post.php?action=fdcrm_updater_notes',
            (new WpAdmin())->postUrl('fdcrm_updater_notes'),
        );
    }

    public function testTheActionIsEncodedInThePostUrl(): void
    {
        $this->assertSame(
            'https://example.test/wp-admin/admin-post.php?action=a%20b%26c%3Dd',
            (new WpAdmin())->postUrl('a b&c=d'),
        );
    }

    public function testACapabilityIsCheckedAgainstTheSignedInPerson(): void
    {
        WpStub::$capabilities = ['update_themes'];
        $admin = new WpAdmin();

        $this->assertTrue($admin->can('update_themes'));
        $this->assertFalse($admin->can('manage_options'));
        $this->assertFalse($admin->can(''));
    }

    public function testRespondingPrintsTheDocumentAndAsksForNoCaching(): void
    {
        ob_start();
        (new WpAdmin())->respond('<!DOCTYPE html><p>Notes</p>');
        $output = ob_get_clean();

        $this->assertSame('<!DOCTYPE html><p>Notes</p>', $output);
        $this->assertSame(1, WpStub::$noCacheHeaders);
    }

    public function testDenyingEndsTheRequestWithA403AndTheCoreMessage(): void
    {
        (new WpAdmin())->deny();

        $this->assertCount(1, WpStub::$dies);
        $this->assertSame('Sorry, you are not allowed to access this page.', WpStub::$dies[0]['message']);
        $this->assertSame(['response' => 403], WpStub::$dies[0]['args']);
        $this->assertContains(['Sorry, you are not allowed to access this page.', 'default'], WpStub::$translated);
    }

    public function testAMenuPageIsAddedUnderItsParentWithOneTitleForPageAndMenu(): void
    {
        $render = static function (): void {
        };

        (new WpAdmin())->addSubmenuPage('options-general.php', 'Updates', 'manage_options', 'fdcrm_updater_settings', $render);

        $this->assertCount(1, WpStub::$submenus);
        $this->assertSame('options-general.php', WpStub::$submenus[0]['parent']);
        $this->assertSame('Updates', WpStub::$submenus[0]['page']);
        $this->assertSame('Updates', WpStub::$submenus[0]['menu']);
        $this->assertSame('manage_options', WpStub::$submenus[0]['capability']);
        $this->assertSame('fdcrm_updater_settings', WpStub::$submenus[0]['slug']);
        $this->assertSame($render, WpStub::$submenus[0]['callback']);
    }

    public function testNonceFieldsAreReturnedAndNotPrinted(): void
    {
        ob_start();
        $fields = (new WpAdmin())->nonceFields('fdcrm_updater_save');
        $printed = ob_get_clean();

        $this->assertSame('', $printed);
        $this->assertStringContainsString('name="_wpnonce"', $fields);
        $this->assertStringContainsString('value="nonce-fdcrm_updater_save"', $fields);
        $this->assertStringContainsString('name="_wp_http_referer"', $fields);
    }

    public function testANonceIsValidOnlyForItsOwnAction(): void
    {
        $admin = new WpAdmin();
        $_REQUEST['_wpnonce'] = 'nonce-fdcrm_updater_save';

        $this->assertTrue($admin->verifyNonce('fdcrm_updater_save'));
        $this->assertFalse($admin->verifyNonce('fdcrm_updater_remove'));
    }

    public function testAMissingOrOddNonceIsNotValid(): void
    {
        $admin = new WpAdmin();

        $this->assertFalse($admin->verifyNonce('fdcrm_updater_save'));

        foreach ([['nonce-fdcrm_updater_save'], 123, null, '', 'nonce-'] as $value) {
            $_REQUEST['_wpnonce'] = $value;

            $this->assertFalse($admin->verifyNonce('fdcrm_updater_save'), var_export($value, true));
        }
    }

    public function testInputIsReadFromThePostedFormAndUnslashed(): void
    {
        $_POST['fdcrm_updater_token'] = "ghp_a\\'b";
        $_GET['fdcrm_updater_token'] = 'from the address';

        $this->assertSame("ghp_a'b", (new WpAdmin())->input('fdcrm_updater_token'));
        $this->assertSame('', (new WpAdmin())->input('missing'));
    }

    public function testInputThatIsNotTextIsAnEmptyString(): void
    {
        $_POST['a'] = ['x'];
        $_POST['b'] = 5;
        $_POST['c'] = null;

        foreach (['a', 'b', 'c'] as $name) {
            $this->assertSame('', (new WpAdmin())->input($name), $name);
        }
    }

    public function testAQueryValueIsReadFromTheAddressAndNotTheForm(): void
    {
        $_GET['fdcrm_updater_notice'] = 'saved';
        $_POST['fdcrm_updater_notice'] = 'from the form';

        $this->assertSame('saved', (new WpAdmin())->query('fdcrm_updater_notice'));
        $this->assertSame('', (new WpAdmin())->query('missing'));
    }

    public function testAQueryValueThatIsNotTextIsAnEmptyString(): void
    {
        $_GET['a'] = ['x'];

        $this->assertSame('', (new WpAdmin())->query('a'));
    }

    public function testRedirectingBackGoesToTheRefererWithTheParameterAdded(): void
    {
        WpStub::$referer = 'https://example.test/wp-admin/options-general.php?page=fdcrm_updater_settings';

        (new WpAdmin())->redirectBack('fdcrm_updater_notice', 'saved');

        $this->assertSame(
            ['https://example.test/wp-admin/options-general.php?page=fdcrm_updater_settings&fdcrm_updater_notice=saved'],
            WpStub::$redirects,
        );
    }

    public function testRedirectingBackReplacesAnEarlierNotice(): void
    {
        WpStub::$referer = 'https://example.test/wp-admin/themes.php?fdcrm_updater_notice=saved&x=1';

        (new WpAdmin())->redirectBack('fdcrm_updater_notice', 'removed');

        $this->assertSame(['https://example.test/wp-admin/themes.php?fdcrm_updater_notice=removed&x=1'], WpStub::$redirects);
    }

    public function testRedirectingBackWithNoRefererGoesToTheAdminHome(): void
    {
        WpStub::$referer = false;
        (new WpAdmin())->redirectBack('fdcrm_updater_notice', 'checked');

        WpStub::$referer = '';
        (new WpAdmin())->redirectBack('fdcrm_updater_notice', 'checked');

        $this->assertSame([
            'https://example.test/wp-admin/?fdcrm_updater_notice=checked',
            'https://example.test/wp-admin/?fdcrm_updater_notice=checked',
        ], WpStub::$redirects);
    }

    public function testTheTimeIsFormattedWithTheSiteFormatsAndAFallback(): void
    {
        $timestamp = 1_800_000_000;

        $this->assertSame(gmdate('F j, Y g:i a', $timestamp), (new WpAdmin())->dateTime($timestamp));

        WpStub::$options['date_format'] = 'Y-m-d';
        WpStub::$options['time_format'] = 'H:i';

        $this->assertSame(gmdate('Y-m-d H:i', $timestamp), (new WpAdmin())->dateTime($timestamp));
    }

    public function testTheTimeFallsBackToAPlainFormatWhenWordPressCannotFormatIt(): void
    {
        WpStub::$dateFails = true;
        $timestamp = 1_800_000_000;

        $this->assertSame(gmdate('Y-m-d H:i', $timestamp), (new WpAdmin())->dateTime($timestamp));
    }
}
