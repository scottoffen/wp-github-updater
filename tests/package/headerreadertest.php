<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Package;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Package\HeaderReader;

final class HeaderReaderTest extends TestCase
{
    private const PLUGIN = <<<'PHP'
<?php
/**
 * Plugin Name: Front Desk CRM
 * Plugin URI:  https://example.com/front-desk-crm
 * Description: A small CRM: leads, notes, and follow-ups.
 * Version:     1.4.2
 * Requires at least: 6.4
 * Author:      Scott Offen
 * Update URI:  https://github.com/owner/repo
 */

namespace Demo;
PHP;

    private function read(string $head, string $name): string
    {
        return (new HeaderReader())->read($head, $name);
    }

    public function testTheVersionAndNameAreReadFromAStandardHeader(): void
    {
        $this->assertSame('1.4.2', $this->read(self::PLUGIN, 'Version'));
        $this->assertSame('Front Desk CRM', $this->read(self::PLUGIN, 'Plugin Name'));
    }

    public function testAValueMayContainAColon(): void
    {
        $this->assertSame('A small CRM: leads, notes, and follow-ups.', $this->read(self::PLUGIN, 'Description'));
        $this->assertSame('https://github.com/owner/repo', $this->read(self::PLUGIN, 'Update URI'));
    }

    public function testSimilarHeadersAreNotConfused(): void
    {
        $this->assertSame('https://example.com/front-desk-crm', $this->read(self::PLUGIN, 'Plugin URI'));
        $this->assertSame('6.4', $this->read(self::PLUGIN, 'Requires at least'));
        $this->assertSame('', $this->read(self::PLUGIN, 'Requires PHP'));
    }

    public function testTheNameOfTheHeaderIsNotCaseSensitive(): void
    {
        $head = "<?php\n/* PLUGIN NAME: Demo\n * version: 2.0.0 */";

        $this->assertSame('Demo', $this->read($head, 'Plugin Name'));
        $this->assertSame('2.0.0', $this->read($head, 'Version'));
    }

    public function testAClosingCommentOnTheSameLineIsNotPartOfTheValue(): void
    {
        $this->assertSame('Demo', $this->read("<?php\n/* Plugin Name: Demo */\n", 'Plugin Name'));
        $this->assertSame('1.0.0', $this->read("<?php\n/* Version: 1.0.0 */ \$x = 1;\n", 'Version'));
        $this->assertSame('1.0.0', $this->read("<?php // Version: 1.0.0 ?>\n", 'Version'));
    }

    public function testEveryLineEndingWorks(): void
    {
        foreach (["\n", "\r\n", "\r"] as $ending) {
            $head = implode($ending, ['<?php', '/*', ' * Plugin Name: Demo', ' * Version: 3.1.4', ' */']);

            $this->assertSame('3.1.4', $this->read($head, 'Version'), bin2hex($ending));
            $this->assertSame('Demo', $this->read($head, 'Plugin Name'), bin2hex($ending));
        }
    }

    public function testCommentCharactersBeforeTheNameAreAllowed(): void
    {
        foreach (['Version: 1.0.0', ' * Version: 1.0.0', '# Version: 1.0.0', '// Version: 1.0.0', '   @Version: 1.0.0', "\tVersion: 1.0.0"] as $line) {
            $this->assertSame('1.0.0', $this->read("<?php\n" . $line . "\n", 'Version'), $line);
        }
    }

    public function testTheHeaderMayShareTheLineWithTheOpeningTag(): void
    {
        $this->assertSame('1.0.0', $this->read("<?php Version: 1.0.0\n", 'Version'));
    }

    public function testTheNameMustStartTheLine(): void
    {
        $this->assertSame('', $this->read("<?php\n// This is the Version: 2.0.0 of the file\n", 'Version'));
        $this->assertSame('', $this->read("<?php\n\$version = 'Version: 2.0.0';\n", 'Version'));
    }

    public function testTheFirstMatchWins(): void
    {
        $this->assertSame('1.0.0', $this->read("<?php\n/*\n * Version: 1.0.0\n */\n/*\n * Version: 2.0.0\n */\n", 'Version'));
    }

    public function testAMissingOrEmptyHeaderIsAnEmptyString(): void
    {
        $this->assertSame('', $this->read("<?php\n/* Plugin Name: Demo */\n", 'Version'));
        $this->assertSame('', $this->read("<?php\n/*\n * Version:\n */\n", 'Version'));
        $this->assertSame('', $this->read('', 'Version'));
    }

    public function testSurroundingWhitespaceIsTrimmed(): void
    {
        $this->assertSame('1.0.0', $this->read("<?php\n/*\n * Version:   \t 1.0.0 \t \n */\n", 'Version'));
    }

    public function testASuffixedVersionIsKeptWhole(): void
    {
        $this->assertSame('1.2.3-beta.1', $this->read("<?php\n/* Version: 1.2.3-beta.1 */\n", 'Version'));
    }

    public function testTheNameIsMatchedLiterallyNotAsAPattern(): void
    {
        $head = "<?php\n/*\n * Plugin Name: Demo\n */\n";

        $this->assertSame('', $this->read($head, 'Plugin.*'));
        $this->assertSame('', $this->read($head, '.*'));
    }
}
