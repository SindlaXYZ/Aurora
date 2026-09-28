<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Composer;

use Composer\Script\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Composer\ScriptHandler;
use Sindla\Bundle\AuroraBundle\Utils\AuroraIO\AuroraIO;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * composer/composer is not a dependency of the bundle: the tests that need a Composer\Script\Event run in a separate process, where the
 * class is an alias of a stub
 */
#[RequiresMethod(PhpExecutableFinder::class, 'find')]
class ScriptHandlerTest extends TestCase
{
    private string $dir = '';
    private string $cwd = '';

    /**
     * @var array<string, string|false> The original environment variables
     */
    private array $env = [];

    protected function tearDown(): void
    {
        foreach ($this->env as $name => $value) {
            putenv(false === $value ? $name : sprintf('%s=%s', $name, $value));
        }

        if ('' !== $this->cwd) {
            chdir($this->cwd);
        }

        if ('' !== $this->dir) {
            new AuroraIO()->recursiveDelete($this->dir);
        }
    }

    public function testGetPhpReturnsThePhpBinaryOfTheEnvironment(): void
    {
        $php = $this->createDir() . '/php';
        file_put_contents($php, "#!/bin/sh\n");
        chmod($php, 0755);
        $this->setEnv('PHP_BINARY', $php);

        $getPhp = new \ReflectionMethod(ScriptHandler::class, 'getPhp');

        $this->assertSame($php, $getPhp->invoke(null));
        $this->assertSame($php, $getPhp->invoke(null, false));
    }

    public function testGetPhpFailsWhenPhpCannotBeFound(): void
    {
        $this->setEnv('PHP_BINARY', sprintf('%s/aurora-missing-%s/php', sys_get_temp_dir(), bin2hex(random_bytes(4))));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The php executable could not be found, add it to your PATH environment variable and try again');

        new \ReflectionMethod(ScriptHandler::class, 'getPhp')->invoke(null, false);
    }

    public function testGetPhpArgumentsUsesTheOriginalIniOfComposer(): void
    {
        // Composer restarts PHP without Xdebug and keeps the original ini files in COMPOSER_ORIGINAL_INIS
        $this->setEnv('COMPOSER_ORIGINAL_INIS', '/example/php.ini' . PATH_SEPARATOR . '/example/conf.d/aurora.ini');

        $this->assertSame(
            [...new PhpExecutableFinder()->findArguments(), '--php-ini=/example/php.ini'],
            new \ReflectionMethod(ScriptHandler::class, 'getPhpArguments')->invoke(null)
        );
    }

    public function testGetPhpArgumentsUsesTheLoadedIni(): void
    {
        $this->setEnv('COMPOSER_ORIGINAL_INIS', null);
        $ini = php_ini_loaded_file();

        $this->assertSame(
            [...new PhpExecutableFinder()->findArguments(), ...(false === $ini ? [] : ['--php-ini=' . $ini])],
            new \ReflectionMethod(ScriptHandler::class, 'getPhpArguments')->invoke(null)
        );
    }

    /**
     * @param array<string, string> $env
     * @param array<string, mixed>  $extra
     * @param array<string, mixed>  $expected
     */
    #[DataProvider('dataGetOptions')]
    #[RunInSeparateProcess]
    public function testGetOptions(array $env, array $extra, array $expected): void
    {
        $this->setEnv('SYMFONY_ASSETS_INSTALL', $env['SYMFONY_ASSETS_INSTALL'] ?? null);
        $this->setEnv('SYMFONY_CACHE_WARMUP', $env['SYMFONY_CACHE_WARMUP'] ?? null);

        $event = $this->createEvent($extra, ['process-timeout' => 600, 'vendor-dir' => '/srv/example/vendor']);

        $this->assertSame($expected, new \ReflectionMethod(ScriptHandler::class, 'getOptions')->invoke(null, $event));
    }

    public static function dataGetOptions(): array
    {
        return [
            'the default options' => [
                [],
                [],
                [
                    'symfony-app-dir'        => 'app',
                    'symfony-web-dir'        => 'web',
                    'symfony-assets-install' => 'hard',
                    'symfony-cache-warmup'   => false,
                    'process-timeout'        => 600,
                    'vendor-dir'             => '/srv/example/vendor',
                ],
            ],
            'the extra of the package' => [
                [],
                ['symfony-web-dir' => 'public', 'symfony-assets-install' => 'relative', 'aurora' => 'extra'],
                [
                    'symfony-app-dir'        => 'app',
                    'symfony-web-dir'        => 'public',
                    'symfony-assets-install' => 'relative',
                    'symfony-cache-warmup'   => false,
                    'aurora'                 => 'extra',
                    'process-timeout'        => 600,
                    'vendor-dir'             => '/srv/example/vendor',
                ],
            ],
            'the environment wins over the extra' => [
                ['SYMFONY_ASSETS_INSTALL' => 'symlink', 'SYMFONY_CACHE_WARMUP' => '1'],
                ['symfony-assets-install' => 'relative'],
                [
                    'symfony-app-dir'        => 'app',
                    'symfony-web-dir'        => 'web',
                    'symfony-assets-install' => 'symlink',
                    'symfony-cache-warmup'   => '1',
                    'process-timeout'        => 600,
                    'vendor-dir'             => '/srv/example/vendor',
                ],
            ],
        ];
    }

    /**
     * @param list<string> $expectedArguments
     */
    #[DataProvider('dataTheComposerHooksRunTheComposerCommandOfTheConsole')]
    #[RunInSeparateProcess]
    public function testTheComposerHooksRunTheComposerCommandOfTheConsole(string $hook, bool $decorated, array $expectedArguments): void
    {
        $this->createConsole("<?php\necho json_encode(array_slice(\$argv, 1));\n");
        $event = $this->createEvent([], ['process-timeout' => 60], $decorated);

        ScriptHandler::$hook($event);

        // The output of the console is written to the Composer IO
        $this->assertSame($expectedArguments, json_decode(implode('', $event->written), true));
    }

    public static function dataTheComposerHooksRunTheComposerCommandOfTheConsole(): array
    {
        return [
            'post install'           => ['postInstall', false, ['aurora:composer', '--action=postInstall']],
            'post update'            => ['postUpdate', false, ['aurora:composer', '--action=postUpdate']],
            'post update, decorated' => ['postUpdate', true, ['aurora:composer', '--action=postUpdate', '--ansi']],
        ];
    }

    #[RunInSeparateProcess]
    public function testAFailedCommandThrowsWithItsUndecoratedOutput(): void
    {
        $this->createConsole("<?php\nfwrite(STDOUT, \"\\033[32mpartial output\\033[0m\");\nfwrite(STDERR, \"\\033[1;31mfatal error\\033[0m\");\nexit(3);\n");

        try {
            ScriptHandler::postUpdate($this->createEvent([], ['process-timeout' => 60]));
            $this->fail('A failed command is expected to throw.');
        } catch (\RuntimeException $e) {
            $this->assertStringStartsWith("An error occurred when executing the \"aurora:composer\" command.\n", $e->getMessage());
            $this->assertStringContainsString(" bin/console aurora:composer --action=postUpdate\nError:\n\npartial output\n\nfatal error", $e->getMessage());
            $this->assertStringNotContainsString("\033[", $e->getMessage());
        }
    }

    #[RunInSeparateProcess]
    public function testTheCommandIsStoppedAfterTheProcessTimeoutOfComposer(): void
    {
        $this->createConsole("<?php\nsleep(30);\n");

        $this->expectException(ProcessTimedOutException::class);

        ScriptHandler::postInstall($this->createEvent([], ['process-timeout' => 1]));
    }

    private function setEnv(string $name, ?string $value): void
    {
        if (!array_key_exists($name, $this->env)) {
            $this->env[$name] = getenv($name);
        }

        putenv(null === $value ? $name : sprintf('%s=%s', $name, $value));
    }

    private function createDir(): string
    {
        $this->dir = sprintf('%s/aurora-script-handler-%s', sys_get_temp_dir(), bin2hex(random_bytes(4)));
        mkdir($this->dir);

        return $this->dir;
    }

    /**
     * The hooks run "bin/console" of the current directory
     */
    private function createConsole(string $script): void
    {
        $dir = $this->createDir();
        mkdir($dir . '/bin');
        file_put_contents($dir . '/bin/console', $script);

        $this->cwd = (string)getcwd();
        chdir($dir);
    }

    /**
     * A Composer\Script\Event, with its Composer, root package, config and IO
     *
     * @param array<string, mixed> $extra
     * @param array<string, mixed> $config
     */
    private function createEvent(array $extra, array $config, bool $decorated = false): object
    {
        $event = new class ($extra, $config, $decorated) {
            /**
             * @var list<string>
             */
            public array $written = [];

            /**
             * @param array<string, mixed> $extra
             * @param array<string, mixed> $config
             */
            public function __construct(
                private readonly array $extra,
                private readonly array $config,
                private readonly bool $decorated,
            ) {
            }

            public function getComposer(): static
            {
                return $this;
            }

            public function getIO(): static
            {
                return $this;
            }

            public function getPackage(): static
            {
                return $this;
            }

            public function getConfig(): static
            {
                return $this;
            }

            /**
             * @return array<string, mixed>
             */
            public function getExtra(): array
            {
                return $this->extra;
            }

            public function get(string $key): mixed
            {
                return $this->config[$key] ?? null;
            }

            public function isDecorated(): bool
            {
                return $this->decorated;
            }

            public function write(string $messages, bool $newline = true): void
            {
                $this->written[] = $messages;
            }
        };

        if (!class_exists(Event::class)) {
            class_alias($event::class, Event::class);
        } elseif (!$event instanceof Event) {
            $this->markTestSkipped('composer/composer is installed: the stub cannot be an alias of Composer\Script\Event.');
        }

        return $event;
    }
}
