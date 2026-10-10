<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\WordPress;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Internal\WordPress\WpFiles;
use ScottOffen\WpGithubUpdater\Tests\Support\WpStub;

final class WpFilesTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        WpStub::reset();
        $this->dir = sys_get_temp_dir() . '/wpgu-files-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        $this->remove($this->dir);
        WpStub::reset();
    }

    private function remove(string $path): void
    {
        if (is_dir($path)) {
            foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $name) {
                $this->remove($path . '/' . $name);
            }

            rmdir($path);

            return;
        }

        if (is_file($path)) {
            unlink($path);
        }
    }

    public function testTheStartOfAFileIsRead(): void
    {
        file_put_contents($this->dir . '/plugin.php', "<?php\n/* Plugin Name: Demo */\n" . str_repeat('x', 20000));

        $head = (new WpFiles())->readHead($this->dir . '/plugin.php');

        $this->assertNotNull($head);
        $this->assertSame(8192, strlen($head));
        $this->assertStringStartsWith("<?php\n/* Plugin Name: Demo */", $head);
    }

    public function testOnlyTheRequestedBytesAreRead(): void
    {
        file_put_contents($this->dir . '/a.txt', 'abcdefghij');

        $this->assertSame('abcd', (new WpFiles())->readHead($this->dir . '/a.txt', 4));
        $this->assertSame('', (new WpFiles())->readHead($this->dir . '/a.txt', 0));
        $this->assertSame('abcdefghij', (new WpFiles())->readHead($this->dir . '/a.txt', 100));
    }

    public function testAFileThatCannotBeReadGivesNull(): void
    {
        $files = new WpFiles();

        $this->assertNull($files->readHead($this->dir . '/missing.php'));
        $this->assertNull($files->readHead($this->dir));
    }

    public function testAnEmptyFileReadsAsAnEmptyString(): void
    {
        file_put_contents($this->dir . '/empty.php', '');

        $this->assertSame('', (new WpFiles())->readHead($this->dir . '/empty.php'));
    }

    public function testATempFileIsAnExistingEmptyFile(): void
    {
        $path = (new WpFiles())->tempFile();

        $this->assertNotNull($path);
        $this->assertFileExists($path);
        $this->assertSame(0, filesize($path));
    }

    public function testNoTempFileGivesNull(): void
    {
        WpStub::$tempFails = true;

        $this->assertNull((new WpFiles())->tempFile());
    }

    public function testAFileIsHashed(): void
    {
        file_put_contents($this->dir . '/a.zip', 'zip bytes');

        $this->assertSame(hash('sha256', 'zip bytes'), (new WpFiles())->hash($this->dir . '/a.zip', 'sha256'));
        $this->assertSame(hash('sha1', 'zip bytes'), (new WpFiles())->hash($this->dir . '/a.zip', 'sha1'));
    }

    public function testAFileThatCannotBeHashedGivesNull(): void
    {
        $files = new WpFiles();

        $this->assertNull($files->hash($this->dir . '/missing.zip', 'sha256'));
        $this->assertNull($files->hash($this->dir, 'sha256'));
    }

    public function testADeletedFileIsGoneAndWentThroughTheWordPressFunction(): void
    {
        file_put_contents($this->dir . '/a.zip', 'x');

        (new WpFiles())->delete($this->dir . '/a.zip');

        $this->assertFileDoesNotExist($this->dir . '/a.zip');
        $this->assertSame([$this->dir . '/a.zip'], WpStub::$deleted);
    }

    public function testDeletingAMissingFileIsFine(): void
    {
        (new WpFiles())->delete($this->dir . '/never-there.zip');

        $this->addToAssertionCount(1);
    }
}
