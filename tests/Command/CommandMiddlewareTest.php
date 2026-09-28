<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Command\Middleware\CommandMiddleware;
use Sindla\Bundle\AuroraBundle\Command\TestCommand;
use Sindla\Bundle\AuroraBundle\Utils\AuroraIO\AuroraIO;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CommandMiddlewareTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $tempPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->tempPaths as $tempPath) {
            new AuroraIO()->recursiveDelete($tempPath);
        }
    }

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

    public function testTryFailsWithoutAnAction(): void
    {
        $tester = new CommandTester($this->createHelperCommand());

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertMatchesRegularExpression('/^\[\d{2}:\d{2}:\d{2}\] Invalid action: not specified\.$/m', $tester->getDisplay());
    }

    /**
     * @param array<string, string|null> $parameters
     */
    #[DataProvider('dataInitializeReadsTheDryRunOption')]
    public function testInitializeReadsTheDryRunOption(array $parameters, bool $expected): void
    {
        $command = $this->createHelperCommand();
        $command->init(new ArrayInput($parameters, $command->getDefinition()), new BufferedOutput());

        $this->assertSame($expected, $command->isDryRun());
    }

    public static function dataInitializeReadsTheDryRunOption(): array
    {
        return [
            'not passed'         => [[], false],
            'passed as a flag'   => [['--dry-run' => null], true],
            'an empty value'     => [['--dry-run' => ''], true],
            'a true value'       => [['--dry-run' => '1'], true],
            'a yes value'        => [['--dry-run' => 'yes'], true],
            'a false value'      => [['--dry-run' => 'false'], false],
            'a zero value'       => [['--dry-run' => '0'], false],
        ];
    }

    public function testOutputWritesTheMessageWithOrWithoutANewLine(): void
    {
        $command = $this->createHelperCommand();
        $output  = $this->initialize($command);

        $command->call('output', 'first', false);
        $command->call('output', ' second');
        $command->call('output', 'third');

        $this->assertSame("first second\nthird\n", $output->fetch());
    }

    public function testReadYamlFileFailsForAMissingFile(): void
    {
        $file = sprintf('%s/aurora-missing-%s.yaml', sys_get_temp_dir(), bin2hex(random_bytes(4)));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(sprintf('File %s does not exists.', $file));

        new \ReflectionMethod(CommandMiddleware::class, 'readYamlFile')->invoke(new CommandMiddleware(), $file);
    }

    #[DataProvider('dataReadYamlFileReturnsAnEmptyArrayForAnEmptyFile')]
    public function testReadYamlFileReturnsAnEmptyArrayForAnEmptyFile(string $content): void
    {
        $this->assertSame([], new \ReflectionMethod(CommandMiddleware::class, 'readYamlFile')->invoke(new CommandMiddleware(), $this->createTempFile($content)));
    }

    public static function dataReadYamlFileReturnsAnEmptyArrayForAnEmptyFile(): array
    {
        return [
            'an empty file'             => [''],
            'a file with only blanks'   => [" \n\t\n"],
        ];
    }

    public function testReadYamlFileFallsBackToTheSimpleParserWhenSymfonyYamlRejectsTheContent(): void
    {
        // Symfony Yaml rejects the tabs used as indentation, and so does ext-yaml when it is loaded: without the PHP warning of
        // yaml_parse(), which would fail the suite (failOnWarning)
        $file = $this->createTempFile("parent:\n\tchild: value\n\tenabled: yes\nother: 3\n");

        $this->assertSame(
            ['parent' => ['child' => 'value', 'enabled' => true], 'other' => 3],
            new \ReflectionMethod(CommandMiddleware::class, 'readYamlFile')->invoke(new CommandMiddleware(), $file)
        );
    }

    #[DataProvider('dataParseSimpleYaml')]
    public function testParseSimpleYaml(string $yaml, array $expected): void
    {
        $this->assertSame($expected, new \ReflectionMethod(CommandMiddleware::class, 'parseSimpleYaml')->invoke(new CommandMiddleware(), $yaml));
    }

    public static function dataParseSimpleYaml(): array
    {
        return [
            'comments, blank lines and document markers are skipped' => [
                "---\n# comment\nkey: value\n\n  # indented comment\n...\n",
                ['key' => 'value'],
            ],
            'Windows line endings' => [
                "first: 1\r\nsecond: 2\r\n",
                ['first' => 1, 'second' => 2],
            ],
            'a dedent closes several levels' => [
                "a:\n  b:\n    c: 1\n  d: 2\ne: 3\n",
                ['a' => ['b' => ['c' => 1], 'd' => 2], 'e' => 3],
            ],
            'a key without a colon' => [
                "flag\nname: aurora\n",
                ['flag' => null, 'name' => 'aurora'],
            ],
            'a value containing colons' => [
                "url: https://example.com:8080/path\n",
                ['url' => 'https://example.com:8080/path'],
            ],
            'a top level list' => [
                "- first\n- 2\n",
                ['first', 2],
            ],
            'nested lists' => [
                "matrix:\n  -\n    - 1\n    - 2\n  -\n    - 3\n",
                ['matrix' => [[1, 2], [3]]],
            ],
            'a dash alone opens a mapping' => [
                "-\n  name: first\n  size: 2\n-\n  name: second\n",
                [['name' => 'first', 'size' => 2], ['name' => 'second']],
            ],
            'an inline key without a value opens a block' => [
                "items:\n  - settings:\n      debug: true\n      level: 3\n  - plain\n",
                ['items' => [['settings' => ['debug' => true, 'level' => 3]], 'plain']],
            ],
            'list items that are not inline maps' => [
                "- http://example.com\n- 'a: b'\n- key:value\n- url: http://example.com\n",
                ['http://example.com', 'a: b', 'key:value', ['url' => 'http://example.com']],
            ],
        ];
    }

    #[DataProvider('dataCastSimpleYamlValue')]
    public function testCastSimpleYamlValue(string $value, mixed $expected): void
    {
        $this->assertSame($expected, new \ReflectionMethod(CommandMiddleware::class, 'castSimpleYamlValue')->invoke(new CommandMiddleware(), $value));
    }

    public static function dataCastSimpleYamlValue(): array
    {
        return [
            'double-quoted with escapes'       => ['"say \"hi\"\tnow"', "say \"hi\"\tnow"],
            'double-quoted number'             => ['"42"', '42'],
            'single-quoted with a quote'       => ["'it''s'", "it's"],
            'empty double-quoted'              => ['""', ''],
            'empty single-quoted'              => ["''", ''],
            'a lone quote'                     => ['"', '"'],
            'inline list'                      => ['[a, 1, true, ~, "b"]', ['a', 1, true, null, 'b']],
            'inline list with a trailing comma' => ['[a, b, ]', ['a', 'b']],
            'empty inline list'                => ['[]', []],
            'true'                             => ['true', true],
            'yes'                              => ['Yes', true],
            'on'                               => ['ON', true],
            'false'                            => ['false', false],
            'no'                               => ['no', false],
            'off'                              => ['Off', false],
            'null'                             => ['NULL', null],
            'tilde'                            => ['~', null],
            'integer'                          => ['42', 42],
            'negative integer'                 => ['-7', -7],
            'float'                            => ['3.14', 3.14],
            'negative float'                   => ['-0.5', -0.5],
            'version string'                   => ['1.2.3', '1.2.3'],
            'plain string'                     => ['aurora', 'aurora'],
        ];
    }

    public function testHasTtyTellsWhetherTheStandardInputIsATerminal(): void
    {
        $this->assertSame(function_exists('posix_isatty') && stream_isatty(STDIN), $this->createHelperCommand()->call('hasTty'));
    }

    public function testTheProgressBarHelpersDoNothingWithoutAProgressBar(): void
    {
        $command = $this->createHelperCommand();
        $output  = $this->initialize($command);

        $command->call('progressBarAdvanceMessage', 'Importing', 2);
        foreach (['progressBarComment', 'progressBarInfo', 'progressBarWarning', 'progressBarError', 'progressBarSuccess'] as $method) {
            $command->call($method, 'Message', 1);
        }
        $command->call('progressBarFinish', true);

        $this->assertSame('', $output->fetch());
        $this->assertSame(0, $command->call('progressBarGetElapsedSeconds'));
        $this->assertSame(0, $command->call('progressBarGetElapsedMinutes'));
        $this->assertSame(0, $command->call('progressBarGetElapsedHours'));
        $this->assertSame(0, $command->call('progressBarGetElapsedDays'));
        $this->assertTrue($command->call('isFirstStep'));
        $this->assertTrue($command->call('isLastStep'));
    }

    public function testProgressBarAdvanceMessage(): void
    {
        $command = $this->createHelperCommand();
        $output  = $this->initialize($command);
        $bar     = $command->call('progressBarCreate', 3);

        $this->assertInstanceOf(ProgressBar::class, $bar);
        $this->assertSame(3, $bar->getMaxSteps());
        $this->assertTrue($command->call('isFirstStep'));
        $this->assertFalse($command->call('isLastStep'));

        $command->call('progressBarAdvanceMessage', 'Importing the first file');

        $display = $output->fetch();
        $this->assertStringContainsString(' 1/3 ', $display);
        $this->assertStringContainsString('Importing the first file', $display);
        $this->assertFalse($command->call('isFirstStep'));
        $this->assertFalse($command->call('isLastStep'));

        $command->call('progressBarAdvanceMessage', 'Importing the last files', 2, false);

        $this->assertSame(3, $bar->getProgress());
        $this->assertSame('Importing the last files', $bar->getMessage());
        $this->assertStringContainsString(' 3/3 ', $output->fetch());
        $this->assertTrue($command->call('isLastStep'));
    }

    public function testCreateProgressBarIsDeprecated(): void
    {
        $command = $this->createHelperCommand();
        $this->initialize($command);

        $this->expectUserDeprecationMessage(
            sprintf('Since sindla/aurora 8.0: The %s::createProgressBar() method is deprecated, use progressBarCreate() instead.', CommandMiddleware::class)
        );

        $this->assertSame(4, $command->call('createProgressBar', 4)->getMaxSteps());
    }

    #[DataProvider('dataTheProgressBarMessagesAreWrittenAboveTheProgressBar')]
    public function testTheProgressBarMessagesAreWrittenAboveTheProgressBar(string $method, int $step, string $expectedMessage): void
    {
        $command = $this->createHelperCommand();
        $output  = $this->initialize($command);
        $bar     = $command->call('progressBarCreate', 5);
        $command->call('progressBarAdvanceMessage', 'Working');
        $output->fetch();

        $command->call($method, 'Something happened', $step);

        $display = $output->fetch();
        $this->assertSame(1 + $step, $bar->getProgress());
        $this->assertStringContainsString($expectedMessage, $display);
        // The progress bar is displayed again after the message
        $this->assertGreaterThan(strpos($display, $expectedMessage), strpos($display, sprintf(' %d/5 ', 1 + $step)));
        $this->assertStringEndsWith(" Working\n", $display);
    }

    public static function dataTheProgressBarMessagesAreWrittenAboveTheProgressBar(): array
    {
        return [
            'comment'           => ['progressBarComment', 0, '// Something happened'],
            'comment and step'  => ['progressBarComment', 2, '// Something happened'],
            'info'              => ['progressBarInfo', 0, '[INFO] Something happened'],
            'info and step'     => ['progressBarInfo', 1, '[INFO] Something happened'],
            'warning'           => ['progressBarWarning', 0, '[WARNING] Something happened'],
            'warning and step'  => ['progressBarWarning', 3, '[WARNING] Something happened'],
            'error'             => ['progressBarError', 0, '[ERROR] Something happened'],
            'error and step'    => ['progressBarError', 1, '[ERROR] Something happened'],
            'success'           => ['progressBarSuccess', 0, '[OK] Something happened'],
            'success and step'  => ['progressBarSuccess', 4, '[OK] Something happened'],
        ];
    }

    #[DataProvider('dataProgressBarFinish')]
    public function testProgressBarFinish(bool $newLineAtTheEnd, string $expectedEnd): void
    {
        $command = $this->createHelperCommand();
        $output  = $this->initialize($command);
        $bar     = $command->call('progressBarCreate', 4);
        $command->call('progressBarAdvanceMessage', 'Working');
        $output->fetch();

        $command->call('progressBarFinish', $newLineAtTheEnd);

        $display = $output->fetch();
        $this->assertSame(4, $bar->getProgress());
        $this->assertSame('', $bar->getMessage());
        $this->assertStringContainsString(' 4/4 ', $display);
        $this->assertStringNotContainsString('Working', $display);
        $this->assertStringEndsWith($expectedEnd, $display);
        $this->assertTrue($command->call('isLastStep'));
    }

    public static function dataProgressBarFinish(): array
    {
        return [
            'without a new line' => [false, "\n \n"],
            'with a new line'    => [true, "\n \n\n"],
        ];
    }

    public function testTheProgressBarElapsedTime(): void
    {
        $command = $this->createHelperCommand();
        $this->initialize($command);
        $bar = $command->call('progressBarCreate', 10);

        // Started 25 hours ago
        new \ReflectionProperty(ProgressBar::class, 'startTime')->setValue($bar, time() - 90000);

        $seconds = $command->call('progressBarGetElapsedSeconds');
        $this->assertGreaterThanOrEqual(90000, $seconds);
        $this->assertLessThan(90010, $seconds);
        $this->assertEqualsWithDelta(1500, $command->call('progressBarGetElapsedMinutes'), 0.2);
        $this->assertEqualsWithDelta(25, $command->call('progressBarGetElapsedHours'), 0.01);
        $this->assertEqualsWithDelta(90000 / 86400, $command->call('progressBarGetElapsedDays'), 0.001);
    }

    #[DataProvider('dataDatabaseTableTruncateRunsTheTruncateStatementOfThePlatform')]
    public function testDatabaseTableTruncateRunsTheTruncateStatementOfThePlatform(AbstractPlatform $platform, bool $cascade, string $expectedSql): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->expects($this->once())->method('executeStatement')->with($expectedSql)->willReturn(0);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $command = $this->createHelperCommand();
        $command->setEntityManager($entityManager);
        $command->call('databaseTableTruncate', 'aurora_log', $cascade);
    }

    public static function dataDatabaseTableTruncateRunsTheTruncateStatementOfThePlatform(): array
    {
        return [
            'PostgreSQL'              => [new PostgreSQLPlatform(), false, 'TRUNCATE aurora_log'],
            'PostgreSQL with cascade' => [new PostgreSQLPlatform(), true, 'TRUNCATE aurora_log CASCADE'],
            'MySQL'                   => [new MySQLPlatform(), true, 'TRUNCATE aurora_log'],
            'SQLite'                  => [new SQLitePlatform(), false, 'DELETE FROM aurora_log'],
        ];
    }

    public function testDatabaseDropDropsTheWholeDatabase(): void
    {
        $dropCommand = new class extends Command {
            /**
             * @var array<string, mixed>
             */
            public array $options = [];

            protected function configure(): void
            {
                $this
                    ->setName('doctrine:schema:drop')
                    ->addOption('full-database', null, InputOption::VALUE_NONE)
                    ->addOption('force', null, InputOption::VALUE_NONE);
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                $this->options = ['full-database' => $input->getOption('full-database'), 'force' => $input->getOption('force')];
                $output->writeln('Database schema dropped.');

                return self::SUCCESS;
            }
        };

        $command     = $this->createHelperCommand();
        $application = new Application();
        $application->addCommand($dropCommand);
        $application->addCommand($command);

        $tester = new CommandTester($command);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--action' => 'dropDatabase']));
        $this->assertSame(['full-database' => true, 'force' => true], $dropCommand->options);
        $this->assertStringContainsString('Database schema dropped.', $tester->getDisplay());
    }

    public function testDatabaseMigrateRunsTheMigrationsWithTheConsoleOfTheProject(): void
    {
        $projectDir = $this->createTempDir();
        mkdir($projectDir . '/bin');
        file_put_contents($projectDir . '/bin/console', "<?php\nfile_put_contents(dirname(__DIR__) . '/argv.json', json_encode(\$argv));\n");

        $command = $this->createHelperCommand();
        $command->setProjectDir($projectDir);
        $command->call('databaseMigrate');

        $this->assertFileExists($projectDir . '/argv.json');
        $this->assertSame(
            [$projectDir . '/bin/console', 'doctrine:migrations:migrate', '-n'],
            json_decode((string)file_get_contents($projectDir . '/argv.json'), true)
        );
    }

    public function testAuditDropAndRecreateSchemaRecreatesThePublicSchemaOfTheAuditConnection(): void
    {
        $executed   = [];
        $connection = $this->createStub(Connection::class);
        $connection->method('prepare')->willReturnCallback(function (string $sql) use (&$executed): Statement {
            $statement = $this->createStub(Statement::class);
            $statement->method('executeQuery')->willReturnCallback(function () use ($sql, &$executed): Result {
                $executed[] = $sql;

                return $this->createStub(Result::class);
            });

            return $statement;
        });

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $managers        = [];
        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $managerRegistry->method('getManager')->willReturnCallback(function (?string $name = null) use (&$managers, $entityManager): EntityManagerInterface {
            $managers[] = $name;

            return $entityManager;
        });

        $command = $this->createHelperCommand();
        $command->setManagerRegistry($managerRegistry);
        $command->call('auditDropAndRecreateSchema');

        $this->assertSame(['DROP SCHEMA public CASCADE', 'CREATE SCHEMA public'], $executed);
        $this->assertSame(['audit', 'audit'], $managers);
    }

    public function testAuditDropAndRecreateSchemaExplainsThatDoctrineIsMissing(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Doctrine is not available: install and enable "doctrine/doctrine-bundle".');

        $this->createHelperCommand()->call('auditDropAndRecreateSchema');
    }

    private function initialize(CommandMiddleware $command): BufferedOutput
    {
        $output = new BufferedOutput();
        $command->init(new ArrayInput([], $command->getDefinition()), $output);

        return $output;
    }

    private function createTempFile(string $content): string
    {
        $this->tempPaths[] = $file = (string)tempnam(sys_get_temp_dir(), 'aurora-yaml-');
        file_put_contents($file, $content);

        return $file;
    }

    private function createTempDir(): string
    {
        $this->tempPaths[] = $dir = sprintf('%s/aurora-middleware-%s', sys_get_temp_dir(), bin2hex(random_bytes(4)));
        mkdir($dir);

        return $dir;
    }

    /**
     * A command that exposes the protected helpers of the middleware
     */
    private function createHelperCommand(): CommandMiddleware
    {
        return new class extends CommandMiddleware {
            public function __construct()
            {
                parent::__construct();
            }

            protected function configure(): void
            {
                $this
                    ->setName('aurora:middleware-helper-test')
                    ->addOption('action', null, InputOption::VALUE_REQUIRED)
                    ->addOption('dry-run', null, InputOption::VALUE_OPTIONAL);
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return $this->try($input, $output, $this);
            }

            public function init(InputInterface $input, OutputInterface $output): void
            {
                $this->initialize($input, $output);
            }

            public function call(string $method, mixed ...$arguments): mixed
            {
                return $this->$method(...$arguments);
            }

            public function isDryRun(): bool
            {
                return $this->dryRun;
            }

            protected function dropDatabase(): int
            {
                $this->databaseDrop();

                return self::SUCCESS;
            }
        };
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
