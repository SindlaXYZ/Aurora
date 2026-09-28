<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Command\ComposerCommand;
use Sindla\Bundle\AuroraBundle\Utils\AuroraIO\AuroraIO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;

class ComposerCommandTest extends TestCase
{
    private const array GEOIP2_DISABLED = [
        'SINDLA_AURORA_GEO_LITE2_COUNTRY' => 'false',
        'SINDLA_AURORA_GEO_LITE2_CITY'    => 'false',
        'SINDLA_AURORA_GEO_LITE2_ASN'     => 'false',
    ];

    private const array GEOIP2_COUNTRY_ONLY = ['SINDLA_AURORA_GEO_LITE2_COUNTRY' => 'true'] + self::GEOIP2_DISABLED;

    /** @var list<string> */
    private array $projectDirs = [];

    /**
     * @var class-string|null
     */
    private ?string $httpsStub = null;

    protected function tearDown(): void
    {
        if (null !== $this->httpsStub) {
            stream_wrapper_restore('https');
        }

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
            'a file inside the var directory'      => ['/var/keep.txt'],
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

    public function testPostUpdateKeepsARecentPHPUnitPhar(): void
    {
        $https = $this->stubHttps([]);

        [$projectDir, $display, $exitCode] = $this->runAction('postUpdate', function (string $projectDir): void {
            mkdir($projectDir . '/vendor/phpunit', 0777, true);
            file_put_contents($projectDir . '/vendor/phpunit/phpunit.phar', 'recent phar');
        }, env: self::GEOIP2_DISABLED);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('skip updating (PHPUnit is too new)', $display);
        $this->assertSame('recent phar', file_get_contents($projectDir . '/vendor/phpunit/phpunit.phar'));
        $this->assertSame([], $https::$urls);
    }

    /**
     * @param (callable(string): void) $prepare
     */
    #[DataProvider('dataPostUpdateDownloadsThePHPUnitPhar')]
    public function testPostUpdateDownloadsThePHPUnitPhar(callable $prepare): void
    {
        $https = $this->stubHttps(['new phar']);

        [$projectDir, $display, $exitCode] = $this->runAction('postUpdate', $prepare, env: self::GEOIP2_DISABLED);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame(['https://phar.phpunit.de/phpunit.phar'], $https::$urls);
        $this->assertSame('new phar', file_get_contents($projectDir . '/vendor/phpunit/phpunit.phar'));
        $this->assertFileDoesNotExist($projectDir . '/vendor/phpunit/phpunit.phar.download');
        $this->assertStringNotContainsString('skip updating', $display);
    }

    public static function dataPostUpdateDownloadsThePHPUnitPhar(): array
    {
        return [
            'a missing PHAR' => [static function (string $projectDir): void {
                mkdir($projectDir . '/vendor/phpunit', 0777, true);
            }],
            'an empty PHAR'  => [static function (string $projectDir): void {
                mkdir($projectDir . '/vendor/phpunit', 0777, true);
                touch($projectDir . '/vendor/phpunit/phpunit.phar');
            }],
            'an old PHAR'    => [static function (string $projectDir): void {
                mkdir($projectDir . '/vendor/phpunit', 0777, true);
                file_put_contents($projectDir . '/vendor/phpunit/phpunit.phar', 'old phar');
                touch($projectDir . '/vendor/phpunit/phpunit.phar', strtotime('-2 days'));
            }],
        ];
    }

    public function testPostUpdateKeepsTheOldPHPUnitPharWhenTheDownloadFails(): void
    {
        $this->stubHttps([null]);

        [$projectDir, $display, $exitCode] = $this->runAction('postUpdate', function (string $projectDir): void {
            mkdir($projectDir . '/vendor/phpunit', 0777, true);
            file_put_contents($projectDir . '/vendor/phpunit/phpunit.phar', 'old phar');
            touch($projectDir . '/vendor/phpunit/phpunit.phar', strtotime('-2 days'));
        }, env: self::GEOIP2_DISABLED);

        // The PHAR is optional: a failed download does not fail "composer update"
        $this->assertSame(Command::SUCCESS, $exitCode);
        // The warning block of the console wraps the long lines
        $this->assertStringContainsString(
            'skipupdatingthePHPUnitPHAR:fopen(https://phar.phpunit.de/phpunit.phar):Failedtoopenstream',
            (string)preg_replace('/\s+/', '', $display)
        );
        $this->assertSame('old phar', file_get_contents($projectDir . '/vendor/phpunit/phpunit.phar'));
        $this->assertFileDoesNotExist($projectDir . '/vendor/phpunit/phpunit.phar.download');
    }

    public function testPostUpdateDeletesTheDownloadWhenThePharCannotBeReplaced(): void
    {
        $this->stubHttps(['new phar']);
        // The warning of rename()
        set_error_handler(static fn(): bool => true, E_WARNING);

        try {
            [$projectDir, $display, $exitCode] = $this->runAction('postUpdate', function (string $projectDir): void {
                // rename() cannot replace a directory
                mkdir($projectDir . '/vendor/phpunit/phpunit.phar', 0777, true);
                touch($projectDir . '/vendor/phpunit/phpunit.phar', strtotime('-2 days'));
            }, env: self::GEOIP2_DISABLED);
        } finally {
            restore_error_handler();
        }

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString(
            sprintf('skipupdatingthePHPUnitPHAR:Cannotwrite%s/vendor/phpunit/phpunit.pharfileondisk.', $projectDir),
            (string)preg_replace('/\s+/', '', $display)
        );
        $this->assertFileDoesNotExist($projectDir . '/vendor/phpunit/phpunit.phar.download');
    }

    public function testTheGeoIP2UpdateReportsAMaxmindDirectoryThatCannotBeCreated(): void
    {
        $https = $this->stubHttps([]);
        // Like the error handler of Symfony in debug mode
        set_error_handler(static function (int $type, string $message): never {
            throw new \ErrorException($message, 0, $type);
        }, E_WARNING);

        try {
            [$projectDir, $display, $exitCode] = $this->runAction('postInstall', function (string $projectDir, Container $container): void {
                touch($projectDir . '/resources');
                $container->setParameter('aurora.resources', $projectDir . '/resources');
            }, env: self::GEOIP2_COUNTRY_ONLY);
        } finally {
            restore_error_handler();
        }

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString(
            sprintf('[AURORA]Cannotcreatemaxminddir"%s/resources/maxmind-geoip2".', $projectDir),
            (string)preg_replace('/\s+/', '', $display)
        );
        $this->assertSame([], $https::$urls);
    }

    public function testTheGeoIP2UpdateRequiresALicenseKey(): void
    {
        $https = $this->stubHttps([]);

        [$projectDir, $display, $exitCode] = $this->runAction('postInstall', function (string $projectDir, Container $container): void {
            $container->setParameter('aurora.maxmind.license_key', ' ');
        }, env: self::GEOIP2_COUNTRY_ONLY);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('[AURORA] Maxmind license key is not set.', $display);
        $this->assertStringContainsString('[AURORA] Check `MAXMIND_LICENSE_KEY=` inside .env file.', $display);
        $this->assertDirectoryDoesNotExist($projectDir . '/var/resources/maxmind-geoip2');
        $this->assertSame([], $https::$urls);
    }

    public function testARecentGeoIP2DatabaseIsNotDownloadedAgain(): void
    {
        $https = $this->stubHttps([]);

        [$projectDir, $display, $exitCode] = $this->runAction('postInstall', function (string $projectDir): void {
            mkdir($projectDir . '/var/resources/maxmind-geoip2', 0777, true);
            file_put_contents($projectDir . '/var/resources/maxmind-geoip2/GeoLite2Country.mmdb', 'recent database');
        }, env: self::GEOIP2_COUNTRY_ONLY);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('skip updating (GeoIP2/GeoLite2Country is too new)', $display);
        $this->assertSame('recent database', file_get_contents($projectDir . '/var/resources/maxmind-geoip2/GeoLite2Country.mmdb'));
        $this->assertSame([], $https::$urls);
    }

    /**
     * @param (callable(string): void)|null $prepare
     */
    #[DataProvider('dataTheGeoIP2DatabaseIsDownloadedAndInstalled')]
    public function testTheGeoIP2DatabaseIsDownloadedAndInstalled(?callable $prepare): void
    {
        $https = $this->stubHttps([$this->createGeoIP2Archive('Country', 'new database')]);

        [$projectDir, $display, $exitCode] = $this->runAction('postInstall', function (string $projectDir, Container $container) use ($prepare): void {
            $container->setParameter('aurora.maxmind.license_key', 'your+key here');
            if (null !== $prepare) {
                $prepare($projectDir);
            }
        }, env: self::GEOIP2_COUNTRY_ONLY);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame(
            ['https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country&license_key=your%2Bkey%20here&suffix=tar.gz'],
            $https::$urls
        );
        $this->assertSame('new database', file_get_contents($projectDir . '/var/resources/maxmind-geoip2/GeoLite2Country.mmdb'));
        $this->assertSame(['GeoLite2Country.mmdb'], array_values(array_diff(scandir($projectDir . '/var/resources/maxmind-geoip2'), ['.', '..'])));
        $this->assertStringContainsString('[AURORA] Updating the Maxmind GeoIP2/GeoIP2Country ... // [AURORA] ... done;', $display);
    }

    public static function dataTheGeoIP2DatabaseIsDownloadedAndInstalled(): array
    {
        return [
            'a missing directory' => [null],
            'an empty database'   => [static function (string $projectDir): void {
                mkdir($projectDir . '/var/resources/maxmind-geoip2', 0777, true);
                touch($projectDir . '/var/resources/maxmind-geoip2/GeoLite2Country.mmdb');
            }],
            'an old database'     => [static function (string $projectDir): void {
                mkdir($projectDir . '/var/resources/maxmind-geoip2', 0777, true);
                file_put_contents($projectDir . '/var/resources/maxmind-geoip2/GeoLite2Country.mmdb', 'old database');
                touch($projectDir . '/var/resources/maxmind-geoip2/GeoLite2Country.mmdb', strtotime('-2 days'));
            }],
        ];
    }

    public function testAGeoIP2DownloadErrorDoesNotShowTheLicenseKey(): void
    {
        $this->stubHttps([null]);

        [$projectDir, $display, $exitCode] = $this->runAction('postInstall', function (string $projectDir, Container $container): void {
            $container->setParameter('aurora.maxmind.license_key', 'your+key here');
        }, env: self::GEOIP2_COUNTRY_ONLY);

        // The error block of the console wraps the long lines
        $display = (string)preg_replace('/\s+/', '', $display);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString(
            'Cannotdownload.tar.gzfilefromgeolite.maxmind.com:fopen(https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country&license_key=***&suffix=tar.gz)',
            $display
        );
        $this->assertStringNotContainsString('your%2Bkey', $display);
        $this->assertFileDoesNotExist($projectDir . '/var/resources/maxmind-geoip2/GeoLite2Country.mmdb');
    }

    public function testAnEmptyGeoIP2DownloadIsNotInstalled(): void
    {
        $this->stubHttps(['']);

        [$projectDir, $display, $exitCode] = $this->runAction('postInstall', env: self::GEOIP2_COUNTRY_ONLY);

        $this->assertSame(Command::SUCCESS, $exitCode);
        // The error block of the console wraps the long lines
        $this->assertSame(1, preg_match('~Cannotwrite(.+)/GeoLite2-Country\.tar\.gzfileondisk\.~', (string)preg_replace('/\s+/', '', $display), $matches), $display);
        // The temporary directory of the download is always deleted
        $this->assertDirectoryDoesNotExist($matches[1]);
        $this->assertFileDoesNotExist($projectDir . '/var/resources/maxmind-geoip2/GeoLite2Country.mmdb');
    }

    public function testACorruptedGeoIP2ArchiveIsDownloadedOnceMore(): void
    {
        $https = $this->stubHttps(['corrupted archive', $this->createGeoIP2Archive('Country', 'new database')]);

        [$projectDir, $display, $exitCode] = $this->runAction('postInstall', env: self::GEOIP2_COUNTRY_ONLY);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertCount(2, $https::$urls);
        $this->assertStringContainsString('retryingonce.', (string)preg_replace('/\s+/', '', $display));
        $this->assertSame('new database', file_get_contents($projectDir . '/var/resources/maxmind-geoip2/GeoLite2Country.mmdb'));
    }

    public function testAGeoIP2ArchiveCorruptedTwiceFailsTheCommand(): void
    {
        $https = $this->stubHttps(['corrupted archive', 'corrupted archive']);

        try {
            $this->runAction('postInstall', env: self::GEOIP2_COUNTRY_ONLY);
            $this->fail('A corrupted archive is expected to fail the command.');
        } catch (\Exception $e) {
            $this->assertStringStartsWith('[AURORA] Could not read the .tar.gz file (', $e->getMessage());
            $this->assertInstanceOf(\UnexpectedValueException::class, $e->getPrevious());
        }

        $this->assertCount(2, $https::$urls);
    }

    public function testAnInvalidGeoIP2TypeIsRejected(): void
    {
        $https   = $this->stubHttps([]);
        $command = $this->createCommand();
        $output  = new BufferedOutput();
        new \ReflectionProperty(ComposerCommand::class, 'io')->setValue($command, new SymfonyStyle(new ArrayInput([]), $output));

        new \ReflectionMethod(ComposerCommand::class, '_updateGeoIP2')->invoke($command, 'Region');

        $this->assertStringContainsString('[AURORA] _updateGeoIP2(Region) invalid type!', $output->fetch());
        $this->assertSame([], $https::$urls);
    }

    public function testTheGeoIPDatabaseIsNotReplacedWhenItCannotBeCopied(): void
    {
        $dir                 = sys_get_temp_dir() . '/aurora-geoip2-test-' . bin2hex(random_bytes(4));
        $this->projectDirs[] = $dir;
        mkdir($dir . '/download', 0777, true);
        file_put_contents($dir . '/GeoLite2-Country.tar.gz', $this->createGeoIP2Archive('Country', 'new database'));

        $install = new \ReflectionMethod(ComposerCommand::class, 'installGeoIP2Database');
        // The warning of copy()
        set_error_handler(static fn(): bool => true, E_WARNING);

        try {
            $install->invoke($this->createCommand(), new \PharData($dir . '/GeoLite2-Country.tar.gz'), $dir . '/download', $dir . '/missing/GeoLite2Country.mmdb');
            $this->fail('A database that cannot be copied is expected to fail.');
        } catch (\RuntimeException $e) {
            $this->assertSame('[AURORA] Cannot copy .mmdb file.', $e->getMessage());
        } finally {
            restore_error_handler();
        }

        $this->assertDirectoryDoesNotExist($dir . '/missing');
    }

    /**
     * @param (callable(string, Container): void) $prepare
     */
    #[DataProvider('dataPostUpdateDoesNotClearAnUnsafeTmpDir')]
    public function testPostUpdateDoesNotClearAnUnsafeTmpDir(callable $prepare, string $keptFile): void
    {
        [$projectDir, $display, $exitCode] = $this->runAction('postUpdate', $prepare, env: self::GEOIP2_DISABLED);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('skip clearing', $display);
        $this->assertFileExists($projectDir . $keptFile);
    }

    public static function dataPostUpdateDoesNotClearAnUnsafeTmpDir(): array
    {
        return [
            // realpath("") is the current directory
            'an empty value'                    => [static function (string $projectDir, Container $container): void {
                $container->setParameter('aurora.tmp', '');
                touch($projectDir . '/var/tmp/keep.txt');
            }, '/var/tmp/keep.txt'],
            'a project without a var directory' => [static function (string $projectDir, Container $container): void {
                rename($projectDir . '/var', $projectDir . '/storage');
                touch($projectDir . '/storage/tmp/keep.txt');
                $container->setParameter('aurora.tmp', $projectDir . '/storage/tmp');
            }, '/storage/tmp/keep.txt'],
        ];
    }

    private function createCommand(): ComposerCommand
    {
        $container = new Container();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        return new ComposerCommand($container);
    }

    /**
     * @param (callable(string, Container): void)|null $prepare
     * @param string                                   $tmpDir  "aurora.tmp", relative to the project directory
     * @param array<string, string>                    $env     The GeoIP2 flags
     *
     * @return array{string, string, int}
     */
    private function runAction(string $action, ?callable $prepare = null, string $tmpDir = '/var/tmp', array $env = []): array
    {
        $projectDir          = sys_get_temp_dir() . '/aurora-composer-' . bin2hex(random_bytes(4));
        $this->projectDirs[] = $projectDir;
        mkdir($projectDir . '/var/tmp', 0777, true);

        $container = new Container();
        $container->setParameter('kernel.project_dir', $projectDir);
        $container->setParameter('aurora.root', $projectDir);
        $container->setParameter('aurora.tmp', $projectDir . $tmpDir);
        $container->setParameter('aurora.resources', $projectDir . '/var/resources');
        $container->setParameter('aurora.maxmind.license_key', 'your-api-key-here');
        $container->set('aurora.io', new AuroraIO());

        if (null !== $prepare) {
            $prepare($projectDir, $container);
        }

        $flags          = ['SINDLA_AURORA_GEO_LITE2_COUNTRY', 'SINDLA_AURORA_GEO_LITE2_CITY', 'SINDLA_AURORA_GEO_LITE2_ASN'];
        $originalEnv    = $_ENV;
        $originalServer = $_SERVER;
        foreach ($flags as $flag) {
            unset($_ENV[$flag], $_SERVER[$flag]);
        }
        foreach ($env as $name => $value) {
            $_ENV[$name] = $value;
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

    /**
     * Serves the given bodies, in this order, to the https:// requests; null: the request fails
     *
     * @param list<string|null> $bodies
     *
     * @return class-string The stub, its static "urls" property lists the requested URLs
     */
    private function stubHttps(array $bodies): string
    {
        $stub = new class {
            /**
             * @var list<string|null>
             */
            public static array $bodies = [];

            /**
             * @var list<string>
             */
            public static array $urls = [];

            /**
             * @var resource|null
             */
            public mixed $context = null;

            private string $body     = '';
            private int    $position = 0;

            public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
            {
                self::$urls[] = $path;

                if (null === $body = array_shift(self::$bodies)) {
                    return false;
                }

                $this->body = $body;

                return true;
            }

            public function stream_read(int $count): string
            {
                $chunk          = substr($this->body, $this->position, $count);
                $this->position += strlen($chunk);

                return $chunk;
            }

            public function stream_eof(): bool
            {
                return $this->position >= strlen($this->body);
            }

            /**
             * @return array<string, int>
             */
            public function stream_stat(): array
            {
                return [];
            }
        };

        $stub::$bodies = $bodies;
        $stub::$urls   = [];

        if (null === $this->httpsStub) {
            stream_wrapper_unregister('https');
            stream_wrapper_register('https', $stub::class);
            $this->httpsStub = $stub::class;
        }

        return $stub::class;
    }

    /**
     * The layout of the MaxMind archives: GeoLite2-Country_YYYYMMDD/GeoLite2-Country.mmdb
     */
    private function createGeoIP2Archive(string $type, string $database): string
    {
        $dir                 = sys_get_temp_dir() . '/aurora-geoip2-test-' . bin2hex(random_bytes(4));
        $this->projectDirs[] = $dir;
        mkdir($dir);

        $tar = new \PharData($dir . '/archive.tar');
        $tar->addFromString(sprintf('GeoLite2-%1$s_20260101/GeoLite2-%1$s.mmdb', $type), $database);
        $tar->addFromString(sprintf('GeoLite2-%s_20260101/LICENSE.txt', $type), 'license');
        $tar->compress(\Phar::GZ);

        return (string)file_get_contents($dir . '/archive.tar.gz');
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
