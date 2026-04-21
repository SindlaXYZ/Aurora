<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraIO;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraIO\AuroraIO;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/IO/IOTest.php --no-coverage
 */
class IOTest extends TestCase
{
    public function testFileIsOlderThan(): void
    {
        $IO = new AuroraIO();

        $tmpFile = tempnam(sys_get_temp_dir(), 'aurora');

        $this->assertFalse($IO->fileIsOlderThan($tmpFile, 1, AuroraIO::TIME_UNIT_MINUTES));
        sleep(3);
        $this->assertTrue($IO->fileIsOlderThan($tmpFile, 1, AuroraIO::TIME_UNIT_SECONDS));
        unlink($tmpFile);
    }

    public function testFileIsOlderThanReturnsFalseForMissingFile(): void
    {
        $IO         = new AuroraIO();
        $missingPath = sys_get_temp_dir() . '/aurora_missing_' . uniqid();

        $this->assertFalse($IO->fileIsOlderThan($missingPath, 1, AuroraIO::TIME_UNIT_SECONDS));
    }

    public function testRecursiveDeleteHandlesHiddenFiles(): void
    {
        $IO  = new AuroraIO();
        $dir = sys_get_temp_dir() . '/aurora_' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/visible.txt', 'visible');
        file_put_contents($dir . '/.hidden', 'hidden');

        $this->assertTrue($IO->recursiveDelete($dir));
        $this->assertDirectoryDoesNotExist($dir);
    }

    public function testRecursiveDeleteKeepsDirectoryWhenFlagFalse(): void
    {
        $IO  = new AuroraIO();
        $dir = sys_get_temp_dir() . '/aurora_' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/file.txt', 'content');

        $this->assertTrue($IO->recursiveDelete($dir, false));
        $this->assertDirectoryExists($dir);
        rmdir($dir);
    }

    public function testRecursiveDeleteReturnsFalseWhenDeletionFails(): void
    {
        if (!function_exists('posix_geteuid') || !function_exists('posix_seteuid')) {
            $this->markTestSkipped('POSIX functions are required for this test.');
        }

        if (posix_geteuid() !== 0) {
            $this->markTestSkipped('This test requires root privileges to drop permissions temporarily.');
        }

        $nobody = posix_getpwnam('nobody');
        if (false === $nobody) {
            $this->markTestSkipped('User "nobody" is required for this test.');
        }

        $IO           = new AuroraIO();
        $dir          = sys_get_temp_dir() . '/aurora_' . uniqid();
        $file         = $dir . '/file.txt';
        $originalEuid = posix_geteuid();

        mkdir($dir, 0700);
        file_put_contents($file, 'content');

        try {
            $this->assertTrue(posix_seteuid((int) $nobody['uid']));
            $this->assertFalse($IO->recursiveDelete($dir, false));
        } finally {
            posix_seteuid($originalEuid);
        }

        try {
            $this->assertFileExists($file);
        } finally {
            unlink($file);
            rmdir($dir);
        }
    }

    public function testDirIsEmptyHandlesMissingDirectory(): void
    {
        $IO          = new AuroraIO();
        $missingPath = sys_get_temp_dir() . '/aurora_missing_' . uniqid();

        $this->assertTrue($IO->dirIsEmpty($missingPath));
    }

    public function testDirIsEmptyDetectsContents(): void
    {
        $IO  = new AuroraIO();
        $dir = sys_get_temp_dir() . '/aurora_' . uniqid();

        mkdir($dir);
        file_put_contents($dir . '/file.txt', 'content');

        try {
            $this->assertFalse($IO->dirIsEmpty($dir));
        } finally {
            unlink($dir . '/file.txt');
            rmdir($dir);
        }
    }
}
