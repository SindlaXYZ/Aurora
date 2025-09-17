<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\IO;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\IO\IO;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/IO/IOTest.php --no-coverage
 */
class IOTest extends TestCase
{
    public function testFake(): void
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
    }

    public function testFileIsOlderThan(): void
    {
        $IO = new IO();

        $tmpFile = tempnam(sys_get_temp_dir(), 'aurora');

        $this->assertFalse($IO->fileIsOlderThan($tmpFile, 1, IO::TIME_UNIT_MINUTES));
        sleep(3);
        $this->assertTrue($IO->fileIsOlderThan($tmpFile, 1, IO::TIME_UNIT_SECONDS));
        unlink($tmpFile);
    }

    public function testRecursiveDeleteHandlesHiddenFiles(): void
    {
        $IO  = new IO();
        $dir = sys_get_temp_dir() . '/aurora_' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/visible.txt', 'visible');
        file_put_contents($dir . '/.hidden', 'hidden');

        $this->assertTrue($IO->recursiveDelete($dir));
        $this->assertDirectoryDoesNotExist($dir);
    }

    public function testRecursiveDeleteKeepsDirectoryWhenFlagFalse(): void
    {
        $IO  = new IO();
        $dir = sys_get_temp_dir() . '/aurora_' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/file.txt', 'content');

        $this->assertTrue($IO->recursiveDelete($dir, false));
        $this->assertDirectoryExists($dir);
        rmdir($dir);
    }
}
