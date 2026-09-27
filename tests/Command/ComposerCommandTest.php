<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Command;

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

    /**
     * @param (callable(string): void)|null $prepare
     *
     * @return array{string, string, int}
     */
    private function runAction(string $action, ?callable $prepare = null): array
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
        $container->setParameter('aurora.tmp', $projectDir . '/var/tmp');
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
