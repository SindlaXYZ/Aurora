<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraIO;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraIO\AuroraIO;
use Symfony\Component\Filesystem\Filesystem;

/**
 * clear; php vendor/phpunit/phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/AuroraIO/AuroraIOTest.php --no-coverage
 */
class AuroraIOTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->temporaryDirectories);
    }

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

    public function testReplaceFileKeepsThePermissionsOfTheReplacedFile(): void
    {
        $dir = $this->createReplaceFileDir();
        file_put_contents($dir . '/database', 'old');
        chmod($dir . '/database', 0644);
        $handle = fopen($dir . '/database', 'r');

        // The new file of a user with a restrictive umask: the PHP workers could no longer read the replaced file
        file_put_contents($dir . '/database.tmp', 'new');
        chmod($dir . '/database.tmp', 0600);

        try {
            new AuroraIO()->replaceFile($dir . '/database.tmp', $dir . '/database');

            clearstatcache();
            $this->assertSame('new', file_get_contents($dir . '/database'));
            $this->assertSame(0644, fileperms($dir . '/database') & 0777);
            $this->assertFileDoesNotExist($dir . '/database.tmp');
            // Atomic: a reader of the replaced file keeps reading the complete old content
            $this->assertSame('old', stream_get_contents($handle));
        } finally {
            fclose($handle);
            new AuroraIO()->recursiveDelete($dir);
        }
    }

    public function testReplaceFileKeepsTheOwnerAndTheGroupOfTheReplacedFile(): void
    {
        if (!function_exists('posix_geteuid') || 0 !== posix_geteuid()) {
            $this->markTestSkipped('Only a privileged user can create a file of another user.');
        }

        $dir = $this->createReplaceFileDir();
        file_put_contents($dir . '/dump.sql', 'old');
        chown($dir . '/dump.sql', 65534);
        chgrp($dir . '/dump.sql', 65534);
        chmod($dir . '/dump.sql', 0640);
        file_put_contents($dir . '/dump.sql.tmp', 'new');

        try {
            new AuroraIO()->replaceFile($dir . '/dump.sql.tmp', $dir . '/dump.sql');

            // A file replaced by root used to belong to root: "root:root 0640", the application user could no longer read it
            clearstatcache();
            $stat = stat($dir . '/dump.sql');
            $this->assertSame('new', file_get_contents($dir . '/dump.sql'));
            $this->assertSame([65534, 65534, 0640], [$stat['uid'], $stat['gid'], $stat['mode'] & 0777]);
        } finally {
            new AuroraIO()->recursiveDelete($dir);
        }
    }

    public function testReplaceFileOfAnotherUserIsOverwrittenInPlace(): void
    {
        if (!function_exists('posix_geteuid') || 0 !== posix_geteuid() || !is_executable('/usr/bin/setpriv')) {
            $this->markTestSkipped('The file of another user is replaced by an unprivileged process (root and setpriv are required).');
        }

        $dir = $this->createReplaceFileDir();
        file_put_contents($dir . '/database', 'old');
        chmod($dir . '/database', 0666);
        $inode = fileinode($dir . '/database');
        file_put_contents($dir . '/database.tmp', 'new');
        chown($dir . '/database.tmp', 65534);

        $autoloader = dirname((new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2) . '/autoload.php';
        $code       = sprintf('require %s; new %s()->replaceFile($argv[1], $argv[2]);', var_export($autoloader, true), AuroraIO::class);

        try {
            // An unprivileged process cannot give the new file to root: it writes into the replaced file, like it used to
            exec(implode(' ', array_map('escapeshellarg', [
                '/usr/bin/setpriv', '--reuid=65534', '--regid=65534', '--clear-groups', PHP_BINARY, '-r', $code, $dir . '/database.tmp', $dir . '/database',
            ])) . ' 2>&1', $output, $exitCode);

            $this->assertSame(0, $exitCode, implode("\n", $output));
            clearstatcache();
            $this->assertSame('new', file_get_contents($dir . '/database'));
            $this->assertSame($inode, fileinode($dir . '/database'));
            $this->assertSame(0, fileowner($dir . '/database'));
            $this->assertFileDoesNotExist($dir . '/database.tmp');
        } finally {
            new AuroraIO()->recursiveDelete($dir);
        }
    }

    public function testReplaceFileWithoutAReplacedFile(): void
    {
        $dir = $this->createReplaceFileDir();
        file_put_contents($dir . '/database.tmp', 'new');

        try {
            new AuroraIO()->replaceFile($dir . '/database.tmp', $dir . '/database');

            $this->assertSame('new', file_get_contents($dir . '/database'));
            $this->assertFileDoesNotExist($dir . '/database.tmp');
        } finally {
            new AuroraIO()->recursiveDelete($dir);
        }
    }

    public function testReplaceFileWritesThroughASymbolicLink(): void
    {
        $dir = $this->createReplaceFileDir();
        file_put_contents($dir . '/target', 'old');
        symlink($dir . '/target', $dir . '/link');
        file_put_contents($dir . '/link.tmp', 'new');

        try {
            new AuroraIO()->replaceFile($dir . '/link.tmp', $dir . '/link');

            $this->assertTrue(is_link($dir . '/link'));
            $this->assertSame('new', file_get_contents($dir . '/target'));
            $this->assertFileDoesNotExist($dir . '/link.tmp');
        } finally {
            new AuroraIO()->recursiveDelete($dir);
        }
    }

    public function testReplaceFileFailureKeepsTheSource(): void
    {
        $dir = $this->createReplaceFileDir();
        file_put_contents($dir . '/database.tmp', 'new');

        try {
            new AuroraIO()->replaceFile($dir . '/database.tmp', $dir . '/missing/database');
            $this->fail('A destination in a missing directory is expected to fail.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Cannot write the file', $e->getMessage());
            $this->assertFileExists($dir . '/database.tmp');
        } finally {
            new AuroraIO()->recursiveDelete($dir);
        }
    }

    public function testRecursiveCreateDirectoryCreatesTheMissingParents(): void
    {
        $IO        = new AuroraIO();
        $directory = $this->createTemporaryDirectory() . '/a/b/c';

        $this->assertTrue($IO->recursiveCreateDirectory($directory));
        $this->assertDirectoryExists($directory);
        // An existing directory
        $this->assertTrue($IO->recursiveCreateDirectory($directory));
    }

    public function testRecursiveCreateDirectoryFailsBelowAFile(): void
    {
        $file = $this->createTemporaryDirectory() . '/file.txt';
        file_put_contents($file, 'content');

        // mkdir() also emits a "Not a directory" warning
        $this->assertFalse(@new AuroraIO()->recursiveCreateDirectory($file . '/directory'));
        $this->assertFileExists($file);
    }

    public function testRecursiveDeleteOfAFileAndOfAMissingPath(): void
    {
        $IO   = new AuroraIO();
        $file = $this->createTemporaryDirectory() . '/file.txt';
        file_put_contents($file, 'content');

        $this->assertTrue($IO->recursiveDelete($file));
        $this->assertFileDoesNotExist($file);
        $this->assertFalse($IO->recursiveDelete($file));
    }

    public function testRecursiveDeleteRemovesASymbolicLinkButNotItsTarget(): void
    {
        $dir    = $this->createTemporaryDirectory();
        $target = $this->createTemporaryDirectory();
        file_put_contents($target . '/keep.txt', 'content');
        symlink($target, $dir . '/link');

        $this->assertTrue(new AuroraIO()->recursiveDelete($dir));
        $this->assertDirectoryDoesNotExist($dir);
        $this->assertFileExists($target . '/keep.txt');
    }

    public function testRecursiveDeleteGoesOnAfterAChildThatCannotBeDeleted(): void
    {
        if (!function_exists('posix_geteuid') || 0 === posix_geteuid()) {
            $this->markTestSkipped('A privileged user can delete a file of a read-only directory.');
        }

        $dir = $this->createTemporaryDirectory();
        mkdir($dir . '/locked');
        file_put_contents($dir . '/locked/file.txt', 'content');
        file_put_contents($dir . '/other.txt', 'content');
        chmod($dir . '/locked', 0500);

        try {
            $this->assertFalse(new AuroraIO()->recursiveDelete($dir));
            $this->assertFileExists($dir . '/locked/file.txt');
            $this->assertFileDoesNotExist($dir . '/other.txt');
        } finally {
            chmod($dir . '/locked', 0700);
        }
    }

    public function testReplaceFileWithAMissingSourceKeepsTheReplacedFile(): void
    {
        $dir = $this->createTemporaryDirectory();
        file_put_contents($dir . '/database', 'old');

        try {
            new AuroraIO()->replaceFile($dir . '/missing.tmp', $dir . '/database');
            $this->fail('A missing source is expected to fail.');
        } catch (\RuntimeException $e) {
            $this->assertSame(sprintf('Cannot write the file "%s".', $dir . '/database'), $e->getMessage());
        }

        $this->assertSame('old', file_get_contents($dir . '/database'));
    }

    public function testDirIsEmpty(): void
    {
        $IO  = new AuroraIO();
        $dir = $this->createTemporaryDirectory();

        $this->assertTrue($IO->dirIsEmpty($dir));

        file_put_contents($dir . '/.hidden', 'hidden');
        $this->assertFalse($IO->dirIsEmpty($dir));
    }

    public function testDirIsEmptyOfAnUnreadableDirectory(): void
    {
        if (!function_exists('posix_geteuid') || 0 === posix_geteuid()) {
            $this->markTestSkipped('Every directory is readable by a privileged user.');
        }

        $dir = $this->createTemporaryDirectory();
        chmod($dir, 0000);

        try {
            $this->assertFalse(new AuroraIO()->dirIsEmpty($dir));
        } finally {
            chmod($dir, 0700);
        }
    }

    public function testFileIsOlderThanComparesTheModificationTime(): void
    {
        $IO   = new AuroraIO();
        $dir  = $this->createTemporaryDirectory();
        $file = $dir . '/file.txt';
        file_put_contents($file, 'content');
        touch($file, time() - 7200);
        clearstatcache();

        $this->assertTrue($IO->fileIsOlderThan($file, 1, AuroraIO::TIME_UNIT_HOURS));
        $this->assertFalse($IO->fileIsOlderThan($file, 3, AuroraIO::TIME_UNIT_HOURS));
        // Not a file
        $this->assertFalse($IO->fileIsOlderThan($dir, 1, AuroraIO::TIME_UNIT_SECONDS));
    }

    private function createTemporaryDirectory(): string
    {
        $dir = sys_get_temp_dir() . '/aurora_io_' . bin2hex(random_bytes(6));
        mkdir($dir);
        $this->temporaryDirectories[] = $dir;

        return $dir;
    }

    private function createReplaceFileDir(): string
    {
        $dir = sys_get_temp_dir() . '/aurora_replace_' . bin2hex(random_bytes(4));
        mkdir($dir);
        // Writable by the unprivileged process of testReplaceFileOfAnotherUserIsOverwrittenInPlace() (it deletes its file)
        chmod($dir, 0777);

        return $dir;
    }
}
