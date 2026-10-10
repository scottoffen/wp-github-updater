<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Package;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Internal\Package\ThemePackage;

final class ThemePackageTest extends TestCase
{
    public function testTheSlugIsTheFolderAndTheKeyIsTheSame(): void
    {
        $package = ThemePackage::fromFile('/var/www/wp-content/themes/greenthumb/functions.php');

        $this->assertSame('/var/www/wp-content/themes/greenthumb/functions.php', $package->file);
        $this->assertSame('greenthumb', $package->slug);
        $this->assertSame('greenthumb', $package->key);
    }

    public function testTheStyleSheetIsNextToFunctionsPhp(): void
    {
        $this->assertSame(
            '/var/www/wp-content/themes/greenthumb/style.css',
            ThemePackage::fromFile('/var/www/wp-content/themes/greenthumb/functions.php')->styleFile,
        );
    }

    public function testWindowsPathsWorkAndPreserveTheirSeparators(): void
    {
        $package = ThemePackage::fromFile('C:\\www\\wp-content\\themes\\greenthumb\\functions.php');

        $this->assertSame('greenthumb', $package->slug);
        $this->assertSame('C:\\www\\wp-content\\themes\\greenthumb\\style.css', $package->styleFile);
    }

    public function testAFileThatIsNotFunctionsPhpIsRejected(): void
    {
        foreach ([
            '/t/greenthumb/inc/updater.php',
            '/t/greenthumb/style.css',
            '/t/greenthumb/Functions.php',
            '/t/greenthumb/functions.php.bak',
            '/t/greenthumb/my-functions.php',
            '/t/greenthumb/',
            'functions',
        ] as $file) {
            try {
                ThemePackage::fromFile($file);
                $this->fail('Accepted ' . $file);
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('functions.php', $e->getMessage(), $file);
            }
        }
    }

    public function testAFolderThatIsNotAPlainSlugIsRejected(): void
    {
        foreach (['/t/my theme/functions.php', '/t/-theme/functions.php', '/t/.hidden/functions.php', '/t/caf' . "\u{e9}" . '/functions.php', '/functions.php', 'functions.php'] as $file) {
            try {
                ThemePackage::fromFile($file);
                $this->fail('Accepted ' . $file);
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testSlugsWithDotsHyphensAndUnderscoresAreFine(): void
    {
        foreach (['a', 'twentytwentyfive', 'My_Theme.2', 'theme-v2', '9lives'] as $slug) {
            $this->assertSame($slug, ThemePackage::fromFile('/t/' . $slug . '/functions.php')->slug, $slug);
        }
    }
}
