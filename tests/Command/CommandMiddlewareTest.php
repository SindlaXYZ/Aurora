<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Command\Middleware\CommandMiddleware;
use Sindla\Bundle\AuroraBundle\Command\TestCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CommandMiddlewareTest extends TestCase
{
    public function testProgressBarPreviousDisplayIsDateTime(): void
    {
        $command = new CommandMiddleware();
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('progressBarPreviousDisplay');

        $this->assertInstanceOf(\DateTimeInterface::class, $property->getValue($command));
    }

    public function testReadYamlFileParsesYaml(): void
    {
        $command = new CommandMiddleware();
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('readYamlFile');

        $tmpFile = tempnam(sys_get_temp_dir(), 'yaml');
        file_put_contents($tmpFile, "foo: bar\n");

        try {
            $result = $method->invoke($command, $tmpFile);
        } finally {
            @unlink($tmpFile);
        }

        $this->assertSame(['foo' => 'bar'], $result);
    }

    public function testReadYamlFileParsesNestedStructuresWithoutSymfonyYaml(): void
    {
        $command     = new CommandMiddleware();
        $reflection  = new \ReflectionClass($command);
        $method      = $reflection->getMethod('readYamlFile');

        $yaml = <<<YAML
parent:
  child: value
  enabled: true
  count: 5
  price: 12.5
  inline: [first, second]
  numbers: [1, 2, 3]
  list:
    - entry-one
    - entry-two
  nestedList:
    - name: foo
    - name: bar
YAML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'yaml');
        file_put_contents($tmpFile, $yaml);

        try {
            $result = $method->invoke($command, $tmpFile);
        } finally {
            @unlink($tmpFile);
        }

        $expected = [
            'parent' => [
                'child'      => 'value',
                'enabled'    => true,
                'count'      => 5,
                'price'      => 12.5,
                'inline'     => ['first', 'second'],
                'numbers'    => [1, 2, 3],
                'list'       => ['entry-one', 'entry-two'],
                'nestedList' => [
                    ['name' => 'foo'],
                    ['name' => 'bar'],
                ],
            ],
        ];

        $this->assertSame($expected, $result);
    }

    public function testTryRunsTheActionOfTheCommand(): void
    {
        $tester = new CommandTester(new TestCommand());

        $this->assertSame(Command::SUCCESS, $tester->execute(['--action' => 'test']));
        $this->assertStringContainsString('It works!', $tester->getDisplay());
    }

    /**
     * "aurora:test --action=databaseDrop" used to drop the whole database: any method of the command could be run
     */
    #[DataProvider('dataTryRejectsTheMethodsThatAreNotActions')]
    public function testTryRejectsTheMethodsThatAreNotActions(string $action): void
    {
        $command = $this->createCommand();
        $tester  = new CommandTester($command);

        $this->assertSame(Command::FAILURE, $tester->execute(['--action' => $action]));
        $this->assertStringContainsString('Invalid action', $tester->getDisplay());
        $this->assertSame([], $command->calls);
    }

    public static function dataTryRejectsTheMethodsThatAreNotActions(): array
    {
        return [
            'middleware database method'  => ['databaseDrop'],
            'middleware audit method'     => ['auditDropAndRecreateSchema'],
            'case-insensitive name'       => ['DATABASEDROP'],
            'Symfony command method'      => ['execute'],
            'Symfony command run()'       => ['run'],
            'private method'              => ['privateAction'],
            'method with a required arg'  => ['actionWithArgument'],
            'static method'               => ['staticAction'],
            'underscore method'           => ['_hiddenAction'],
            'unknown method'              => ['doesNotExist'],
        ];
    }

    public function testTryReturnsSuccessForAVoidAction(): void
    {
        $command = $this->createCommand();
        $tester  = new CommandTester($command);

        // A void action returned null from execute(): a TypeError
        $this->assertSame(Command::SUCCESS, $tester->execute(['--action' => 'voidAction']));
        $this->assertSame(['voidAction'], $command->calls);
    }

    public function testTryReturnsTheExitCodeOfTheAction(): void
    {
        $command = $this->createCommand();

        $this->assertSame(Command::INVALID, new CommandTester($command)->execute(['--action' => 'invalidAction']));
        $this->assertSame(Command::FAILURE, new CommandTester($command)->execute(['--action' => 'falseAction']));
    }

    public function testTheCommandsDoNotRequireDoctrine(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->register('aurora.command.test', TestCommand::class)->setAutowired(true)->setPublic(true);

        // The #[Required] Doctrine setters stopped the container of a host without DoctrineBundle from compiling
        $container->compile();

        $this->assertInstanceOf(TestCommand::class, $container->get('aurora.command.test'));
    }

    public function testTheDatabaseHelpersExplainThatDoctrineIsMissing(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Doctrine is not available');

        new \ReflectionMethod(CommandMiddleware::class, 'databaseTableTruncate')->invoke(new CommandMiddleware(), 'aurora');
    }

    private function createCommand(): CommandMiddleware
    {
        return new class extends CommandMiddleware {
            /** @var list<string> */
            public array $calls = [];

            public function __construct()
            {
                parent::__construct();
            }

            protected function configure(): void
            {
                $this
                    ->setName('aurora:middleware-test')
                    ->addOption('action', null, InputOption::VALUE_REQUIRED);
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return $this->try($input, $output, $this);
            }

            protected function databaseDrop(): void
            {
                $this->calls[] = __FUNCTION__;
            }

            protected function voidAction(): void
            {
                $this->calls[] = __FUNCTION__;
            }

            protected function invalidAction(): int
            {
                return self::INVALID;
            }

            protected function falseAction(): bool
            {
                return false;
            }

            protected function actionWithArgument(string $argument): int
            {
                $this->calls[] = __FUNCTION__;

                return self::SUCCESS;
            }

            protected static function staticAction(): int
            {
                return self::SUCCESS;
            }

            protected function _hiddenAction(): int
            {
                $this->calls[] = __FUNCTION__;

                return self::SUCCESS;
            }

            private function privateAction(): int
            {
                $this->calls[] = __FUNCTION__;

                return self::SUCCESS;
            }
        };
    }
}
