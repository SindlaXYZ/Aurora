<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Command\ComposerCommand;
use Sindla\Bundle\AuroraBundle\Utils\AuroraIO\AuroraIO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;

class ComposerCommandTest extends TestCase
{
    /** @var list<string> */
    private array $projectDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->projectDirs as $projectDir) {
            new AuroraIO()->recursiveDelete($projectDir);
        }
    }

    public function testMissingActionFailsGracefully(): void
    {
        $container = new Container();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $tester = new CommandTester(new ComposerCommand($container));

        // The "action" option defaults to null: trim(null) used to be a TypeError (strict_types)
        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('Invalid action: not specified.', $tester->getDisplay());
    }

    public function testOnlyThePostInstallAndPostUpdateActionsCanBeRun(): void
    {
        $container = new Container();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        foreach (['configure', 'execute', '_updatePHPUnit', 'unknownAction'] as $action) {
            $tester = new CommandTester(new ComposerCommand($container));

            // Any method used to be run: "--action=execute" was an infinite recursion
            $this->assertSame(Command::FAILURE, $tester->execute(['--action' => $action]), $action);
            $this->assertStringContainsString(sprintf('Invalid action %s()', $action), $tester->getDisplay(), $action);
        }
    }

    public function testPostUpdateDoesNotFailWithoutThePHPUnitDirectory(): void
    {
        // Lowercase: the action names are case-insensitive, like the method names they used to be looked up as
        [$projectDir, $display, $exitCode] = $this->runAction('postupdate');

        // vendor/phpunit/ does not exist (e.g. "--no-dev"): writing the downloaded PHAR failed, so "composer update" failed
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('skip updating', $display);
        $this->assertFileDoesNotExist($projectDir . '/vendor/phpunit/phpunit.phar');
    }

    public function testTheGeoIPFlagsAreReadFromTheEnvironment(): void
    {
        putenv('SINDLA_AURORA_GEO_LITE2_ASN=false');

        try {
            [, $display, $exitCode] = $this->runAction('postInstall');
        } finally {
            putenv('SINDLA_AURORA_GEO_LITE2_ASN');
        }

        // $_ENV is empty with variables_order = "GPCS" (e.g. Docker): the flags were "not defined", and all three were required
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('SINDLA_AURORA_GEO_LITE2_ASN=false', $display);
        $this->assertStringContainsString('SINDLA_AURORA_GEO_LITE2_COUNTRY is not defined', $display);
    }

    public function testTheOldCompiledAssetsAreDeleted(): void
    {
        [$projectDir, , $exitCode] = $this->runAction('postInstall', function (string $projectDir): void {
            mkdir($projectDir . '/public/static/compiled', 0777, true);
            foreach (['old.css', 'old.js', 'new.css', 'new.js', 'old.txt'] as $file) {
                touch($projectDir . '/public/static/compiled/' . $file, str_starts_with($file, 'old') ? strtotime('-40 days') : time());
            }
        });

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame(['new.css', 'new.js', 'old.txt'], array_values(array_diff(scandir($projectDir . '/public/static/compiled'), ['.', '..'])));
    }

    public function testPostUpdateClearsTheTmpDir(): void
    {
        [$projectDir, , $exitCode] = $this->runAction('postUpdate', function (string $projectDir): void {
            mkdir($projectDir . '/var/tmp/sub', 0777, true);
            touch($projectDir . '/var/tmp/old.txt');
            touch($projectDir . '/var/tmp/sub/old.txt');
        });

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertDirectoryExists($projectDir . '/var/tmp');
        $this->assertSame(['.', '..'], scandir($projectDir . '/var/tmp'));
    }

    public function testPostUpdateDoesNotWarnWhenTheTmpDirDoesNotExist(): void
    {
        [, $display, $exitCode] = $this->runAction('postUpdate', null, '/var/missing');

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringNotContainsString('skip clearing', $display);
    }

    /**
     * The content of "aurora.tmp" was deleted whatever it was: "" is "/", and "/tmp" or the project directory lost all their files
     */
    #[DataProvider('dataPostUpdateDoesNotClearATmpDirOutsideTheVarDir')]
    public function testPostUpdateDoesNotClearATmpDirOutsideTheVarDir(string $tmpDir): void
    {
        [$projectDir, $display, $exitCode] = $this->runAction('postUpdate', function (string $projectDir): void {
            touch($projectDir . '/composer.json');
            touch($projectDir . '/var/keep.txt');
            symlink($projectDir, $projectDir . '/var/tmp/project');
        }, $tmpDir);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('skip clearing', $display);
        $this->assertFileExists($projectDir . '/composer.json');
        $this->assertFileExists($projectDir . '/var/keep.txt');
    }

    public static function dataPostUpdateDoesNotClearATmpDirOutsideTheVarDir(): array
    {
        return [
            'the project directory'                => [''],
            'the var directory'                    => ['/var'],
            'a symbolic link to the project'       => ['/var/tmp/project'],
            'a relative path out of the var dir'   => ['/var/tmp/../..'],
        ];
    }

    /**
     * copy() used to overwrite the live database in place: the running PHP workers read a half-written database
     */
    public function testTheGeoIPDatabaseIsReplacedAtomically(): void
    {
        $dir                 = sys_get_temp_dir() . '/aurora-geoip2-test-' . bin2hex(random_bytes(4));
        $this->projectDirs[] = $dir;
        mkdir($dir . '/download', 0777, true);
        mkdir($dir . '/maxmind-geoip2', 0777, true);

        $destinationFile = $dir . '/maxmind-geoip2/GeoLite2Country.mmdb';
        file_put_contents($destinationFile, 'old database');
        chmod($destinationFile, 0644);
        $handle = fopen($destinationFile, 'r');

        // The layout of the MaxMind archives: GeoLite2-Country_YYYYMMDD/GeoLite2-Country.mmdb
        $tar = new \PharData($dir . '/GeoLite2-Country.tar');
        $tar->addFromString('GeoLite2-Country_20260101/GeoLite2-Country.mmdb', 'new database');
        $tar->addFromString('GeoLite2-Country_20260101/LICENSE.txt', 'license');
        $tar->compress(\Phar::GZ);

        $install = new \ReflectionMethod(ComposerCommand::class, 'installGeoIP2Database');
        // Composer run by a deployment account with a restrictive umask: the new database was not readable by the PHP workers
        $umask = umask(0077);

        try {
            $install->invoke($this->createCommand(), new \PharData($dir . '/GeoLite2-Country.tar.gz'), $dir . '/download', $destinationFile);
        } finally {
            umask($umask);
        }

        clearstatcache();
        $this->assertSame('new database', file_get_contents($destinationFile));
        $this->assertSame(0644, fileperms($destinationFile) & 0777);
        // A reader opened before the update keeps reading the complete old database
        $this->assertSame('old database', stream_get_contents($handle));
        $this->assertSame(['GeoLite2Country.mmdb'], array_values(array_diff(scandir($dir . '/maxmind-geoip2'), ['.', '..'])));

        fclose($handle);
    }

    public function testAnArchiveWithoutADatabaseDoesNotReplaceTheLiveOne(): void
    {
        $dir                 = sys_get_temp_dir() . '/aurora-geoip2-test-' . bin2hex(random_bytes(4));
        $this->projectDirs[] = $dir;
        mkdir($dir . '/download', 0777, true);

        $destinationFile = $dir . '/GeoLite2Country.mmdb';
        file_put_contents($destinationFile, 'old database');

        $tar = new \PharData($dir . '/GeoLite2-Country.tar');
        $tar->addFromString('GeoLite2-Country_20260101/LICENSE.txt', 'license');
        $tar->compress(\Phar::GZ);

        $install = new \ReflectionMethod(ComposerCommand::class, 'installGeoIP2Database');

        try {
            $install->invoke($this->createCommand(), new \PharData($dir . '/GeoLite2-Country.tar.gz'), $dir . '/download', $destinationFile);
            $this->fail('An archive without a database is expected to fail.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('does not contain a .mmdb file', $e->getMessage());
        }

        $this->assertSame('old database', file_get_contents($destinationFile));
    }

    private function createCommand(): ComposerCommand
    {
        $container = new Container();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        return new ComposerCommand($container);
    }

    /**
     * @param (callable(string): void)|null $prepare
     * @param string                        $tmpDir  "aurora.tmp", relative to the project directory
     *
     * @return array{string, string, int}
     */
    private function runAction(string $action, ?callable $prepare = null, string $tmpDir = '/var/tmp'): array
    {
        $projectDir          = sys_get_temp_dir() . '/aurora-composer-' . bin2hex(random_bytes(4));
        $this->projectDirs[] = $projectDir;
        mkdir($projectDir . '/var/tmp', 0777, true);

        if (null !== $prepare) {
            $prepare($projectDir);
        }

        $container = new Container();
        $container->setParameter('kernel.project_dir', $projectDir);
        $container->setParameter('aurora.root', $projectDir);
        $container->setParameter('aurora.tmp', $projectDir . $tmpDir);
        $container->set('aurora.io', new AuroraIO());

        $flags          = ['SINDLA_AURORA_GEO_LITE2_COUNTRY', 'SINDLA_AURORA_GEO_LITE2_CITY', 'SINDLA_AURORA_GEO_LITE2_ASN'];
        $originalEnv    = $_ENV;
        $originalServer = $_SERVER;
        foreach ($flags as $flag) {
            unset($_ENV[$flag], $_SERVER[$flag]);
        }

        try {
            $tester   = new CommandTester(new ComposerCommand($container));
            $exitCode = $tester->execute(['--action' => $action]);

            // Without the line breaks of the console
            return [$projectDir, (string)preg_replace('/\s+/', ' ', $tester->getDisplay()), $exitCode];
        } finally {
            $_ENV    = $originalEnv;
            $_SERVER = $originalServer;
        }
    }

    public function testADownloadErrorDoesNotContainTheSecret(): void
    {
        $container = new Container();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $open = new \ReflectionMethod(ComposerCommand::class, 'open');

        try {
            $open->invoke(new ComposerCommand($container), 'file:///aurora-does-not-exist/download?license_key=my-license-key', 'my-license-key');
            $this->fail('A download error is expected.');
        } catch (\RuntimeException $e) {
            // The PHP warning of fopen() contains the URL: the MaxMind license key was printed and logged
            $this->assertStringNotContainsString('my-license-key', $e->getMessage());
            $this->assertStringContainsString('license_key=***', $e->getMessage());
        }
    }
}
