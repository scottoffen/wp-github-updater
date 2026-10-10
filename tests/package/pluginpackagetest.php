<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Package;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Internal\Package\PluginPackage;

final class PluginPackageTest extends TestCase
{
    private const DIR = '/var/www/wp-content/plugins';

    public function testTheSlugIsTheFolderAndTheKeyIsTheFolderAndFile(): void
    {
        $package = PluginPackage::fromFile(self::DIR . '/front-desk-crm/front-desk-crm.php', self::DIR);

        $this->assertSame(self::DIR . '/front-desk-crm/front-desk-crm.php', $package->file);
        $this->assertSame('front-desk-crm', $package->slug);
        $this->assertSame('front-desk-crm/front-desk-crm.php', $package->key);
    }

    public function testTheMainFileNeedNotMatchTheSlug(): void
    {
        $package = PluginPackage::fromFile(self::DIR . '/my-plugin/plugin.php', self::DIR);

        $this->assertSame('my-plugin', $package->slug);
        $this->assertSame('my-plugin/plugin.php', $package->key);
    }

    public function testWindowsPathsWork(): void
    {
        $package = PluginPackage::fromFile('C:\\www\\wp-content\\plugins\\my-plugin\\my-plugin.php', 'C:\\www\\wp-content\\plugins\\');

        $this->assertSame('my-plugin', $package->slug);
        $this->assertSame('my-plugin/my-plugin.php', $package->key);
    }

    public function testTheFileIsStoredAsGiven(): void
    {
        $file = 'C:\\www\\plugins\\my-plugin\\my-plugin.php';

        $this->assertSame($file, PluginPackage::fromFile($file)->file);
    }

    public function testNoPluginsFolderMeansNoSingleFileCheck(): void
    {
        $this->assertSame('plugins', PluginPackage::fromFile(self::DIR . '/my-plugin.php')->slug);
    }

    public function testASingleFilePluginInThePluginsFolderIsRejected(): void
    {
        foreach ([self::DIR, self::DIR . '/', 'C:\\www\\wp-content\\plugins'] as $dir) {
            $file = $dir === 'C:\\www\\wp-content\\plugins' ? $dir . '\\hello.php' : self::DIR . '/hello.php';

            try {
                PluginPackage::fromFile($file, $dir);
                $this->fail('Accepted a single-file plugin with ' . $dir);
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('folder inside the plugins folder', $e->getMessage());
            }
        }
    }

    public function testAFileThatIsNotPhpIsRejected(): void
    {
        foreach (['/p/my-plugin/readme.txt', '/p/my-plugin/plugin', '/p/my-plugin/'] as $file) {
            try {
                PluginPackage::fromFile($file, '/p');
                $this->fail('Accepted ' . $file);
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAnUppercasePhpExtensionIsFine(): void
    {
        $this->assertSame('my-plugin/Plugin.PHP', PluginPackage::fromFile('/p/my-plugin/Plugin.PHP', '/p')->key);
    }

    public function testAFolderThatIsNotAPlainSlugIsRejected(): void
    {
        foreach (['/p/my plugin/a.php', '/p/-plugin/a.php', '/p/.hidden/a.php', '/p/caf' . "\u{e9}" . '/a.php', '/p/my;plugin/a.php', '/a.php', 'a.php'] as $file) {
            try {
                PluginPackage::fromFile($file, '/nowhere');
                $this->fail('Accepted ' . $file);
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testSlugsWithDotsHyphensAndUnderscoresAreFine(): void
    {
        foreach (['a', 'my-plugin', 'My_Plugin.2', 'plugin.v2', '9lives'] as $slug) {
            $this->assertSame($slug, PluginPackage::fromFile('/p/' . $slug . '/main.php', '/p')->slug, $slug);
        }
    }
}
