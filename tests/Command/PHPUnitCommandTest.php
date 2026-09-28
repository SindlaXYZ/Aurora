<?php
declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\ParameterBag;

if (!interface_exists(ParameterBagInterface::class)) {
    interface ParameterBagInterface
    {
        public function get(string $name);
    }
}

namespace Symfony\Component\Console\Command;

if (!class_exists(Command::class)) {
    abstract class Command
    {
        public const SUCCESS = 0;
        public const FAILURE = 1;

        protected ?string $name;

        public function __construct(?string $name = null)
        {
            $this->name = $name;

            if (method_exists($this, 'configure')) {
                $this->configure();
            }
        }

        public function setHelp(?string $help): static
        {
            return $this;
        }

        public function addOption(
            string            $name,
            string|array|null $shortcut = null,
            ?int              $mode = null,
            string            $description = '',
            mixed             $default = null
        ): static
        {
            return $this;
        }

        public function getName(): ?string
        {
            return $this->name;
        }
    }
}

namespace Symfony\Component\Finder;

if (!class_exists(Finder::class)) {
    class Finder implements \IteratorAggregate
    {
        private bool    $onlyFiles   = false;
        private ?string $directory   = null;
        private ?string $namePattern = null;
        /** @var SplFileInfo[] */
        private array $files = [];

        public function files(): self
        {
            $this->onlyFiles = true;
            return $this;
        }

        public function in(string $directory): self
        {
            $this->directory = realpath($directory) ?: $directory;
            $this->refresh();

            return $this;
        }

        public function name(string $pattern): self
        {
            $this->namePattern = $pattern;
            $this->refresh();

            return $this;
        }

        public function hasResults(): bool
        {
            return !empty($this->files);
        }

        public function getIterator(): \Traversable
        {
            return new \ArrayIterator($this->files);
        }

        private function refresh(): void
        {
            if ($this->directory === null || !\is_dir($this->directory)) {
                $this->files = [];
                return;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS)
            );

            $files = [];

            foreach ($iterator as $file) {
                if ($this->onlyFiles && !$file->isFile()) {
                    continue;
                }

                if ($this->namePattern !== null && !$this->matchesPattern($file->getFilename())) {
                    continue;
                }

                $files[] = new SplFileInfo($file->getPathname(), $this->directory);
            }

            $this->files = $files;
        }

        private function matchesPattern(string $filename): bool
        {
            if ($this->namePattern === null) {
                return true;
            }

            $escaped = str_replace(['*', '?'], ['.*', '.'], preg_quote($this->namePattern, '/'));
            $regex   = '/^' . $escaped . '$/i';

            return (bool)preg_match($regex, $filename);
        }
    }

    class SplFileInfo
    {
        public function __construct(
            private readonly string $pathname,
            private readonly string $baseDirectory
        )
        {
        }

        public function getRealPath(): string
        {
            return $this->pathname;
        }

        public function getFilename(): string
        {
            return basename($this->pathname);
        }

        public function getRelativePathname(): string
        {
            $base = rtrim($this->baseDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

            if (str_starts_with($this->pathname, $base)) {
                return substr($this->pathname, strlen($base));
            }

            return $this->pathname;
        }
    }
}

namespace Sindla\Bundle\AuroraBundle\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Command\Middleware\CommandMiddleware;
use Sindla\Bundle\AuroraBundle\Command\PHPUnitCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

final class PHPUnitCommandTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/aurora_phpunit_command_' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->projectDir . '/results', 0777, true));
        self::assertTrue(mkdir($this->projectDir . '/badges', 0777, true));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    #[Test]
    public function testUpdateDocumentationBlocksKeepsPhpAttributesAttached(): void
    {
        $temporaryRoot = sys_get_temp_dir() . '/aurora_phpunit_' . uniqid('', true);
        $testsRoot     = $temporaryRoot . '/tests/Integration';

        self::assertTrue(mkdir($testsRoot, 0777, true), 'Failed to create the temporary tests directory.');

        $sourceFile = $testsRoot . '/GivenTest.php';
        $fixture    = __DIR__ . '/PHPUnit/GivenTest.php';
        $expected   = __DIR__ . '/PHPUnit/ExpectedTest.php';

        $original = \file_get_contents($fixture);
        self::assertNotFalse($original, 'Fixture content could not be read.');
        self::assertTrue(file_put_contents($sourceFile, $original) !== false, 'Could not create the working test file.');

        $parameterBag = new ParameterBag(['kernel.project_dir' => $temporaryRoot]);

        try {
            $command = new PHPUnitCommand($parameterBag);

            $input  = new ArrayInput([]);
            $output = new BufferedOutput();

            $initialize = new \ReflectionMethod(CommandMiddleware::class, 'initialize');
            $initialize->invoke($command, $input, $output);

            $method = new \ReflectionMethod(PHPUnitCommand::class, 'updateDocumentationBlocks');

            self::assertSame(
                PHPUnitCommand::SUCCESS,
                $method->invoke($command),
                'The command should finish successfully.'
            );

            $result          = \file_get_contents($sourceFile);
            $result          = \str_ireplace('class GivenTest', 'class ExpectedTest', $result);
            $expectedContent = \file_get_contents($expected);

            self::assertNotFalse($result, 'The updated file could not be read.');
            self::assertNotFalse($expectedContent, 'The expected file could not be read.');
            self::assertSame($expectedContent, $result, 'The updated file does not match the expected content.');
        } finally {
            $this->removeDirectory($temporaryRoot);
        }
    }

    #[Test]
    public function testGeneratePHPUnitCoverageHistoryAppendsHistoryAndGeneratesTrendBadge(): void
    {
        $temporaryRoot = sys_get_temp_dir() . '/aurora_phpunit_history_' . uniqid('', true);

        self::assertTrue(mkdir($temporaryRoot . '/test-results', 0777, true), 'Failed to create the temporary test-results directory.');
        self::assertTrue(mkdir($temporaryRoot . '/badges', 0777, true), 'Failed to create the temporary badges directory.');

        $cloverXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
    <project>
        <metrics elements="10" coveredelements="6"/>
        <file name="Sample.php">
            <metrics statements="5" coveredstatements="3"/>
        </file>
    </project>
</coverage>
XML;

        self::assertNotFalse(file_put_contents($temporaryRoot . '/test-results/clover.xml', $cloverXml));

        $originalGitHubSha = \getenv('GITHUB_SHA');
        \putenv('GITHUB_SHA=1234567890abcdef');

        $parameterBag = new ParameterBag(['kernel.project_dir' => $temporaryRoot]);

        try {
            $command = new PHPUnitCommand($parameterBag);

            // Relative paths that do not exist yet: they must be resolved against kernel.project_dir
            $input = new ArrayInput([
                '--cloverXMLFilePath'      => 'test-results/clover.xml',
                '--historyNDJSONFilePath'  => 'badges/coverage-trend.ndjson',
                '--outputTrendSVGFilePath' => 'badges/coverage-trend.svg',
            ], $command->getDefinition());

            $output = new BufferedOutput();

            $initialize = new \ReflectionMethod(CommandMiddleware::class, 'initialize');
            $initialize->invoke($command, $input, $output);

            $method = new \ReflectionMethod(PHPUnitCommand::class, 'generatePHPUnitCoverageHistory');

            self::assertSame(
                PHPUnitCommand::SUCCESS,
                $method->invoke($command),
                'The command should finish successfully.'
            );

            $historyContent = file_get_contents($temporaryRoot . '/badges/coverage-trend.ndjson');
            self::assertIsString($historyContent, 'The coverage history file should have been created.');

            $lines = array_values(array_filter(explode("\n", $historyContent)));
            self::assertCount(1, $lines);

            $entry = json_decode($lines[0], true);
            self::assertIsArray($entry);
            self::assertSame('1234567', $entry['sha']);
            self::assertSame(60, $entry['coverage']);
            self::assertSame(5, $entry['statements']);
            self::assertSame(3, $entry['coveredStatements']);

            $trendSvg = file_get_contents($temporaryRoot . '/badges/coverage-trend.svg');
            self::assertIsString($trendSvg, 'The coverage trend badge should have been created.');
            self::assertStringContainsString('<polyline', $trendSvg);
            self::assertStringContainsString('60% &#183; 3/5', $trendSvg);
        } finally {
            if (false === $originalGitHubSha) {
                \putenv('GITHUB_SHA');
            } else {
                \putenv('GITHUB_SHA=' . $originalGitHubSha);
            }

            $this->removeDirectory($temporaryRoot);
        }
    }

    public function testTestActionPrintsTheEnvironment(): void
    {
        $tester = $this->commandTester();

        self::assertSame(PHPUnitCommand::SUCCESS, $tester->execute(['--action' => 'test']));

        $display = $tester->getDisplay();
        self::assertMatchesRegularExpression('/^\[\d{2}:\d{2}:\d{2}\] Command: aurora:php-unit$/m', $display);
        self::assertStringContainsString('] Application environment: test', $display);
        self::assertStringContainsString(sprintf('] Project directory: %s', $this->projectDir), $display);
    }

    /**
     * @return iterable<string, array{0: array<string, string>}>
     */
    public static function dataMissingRequiredOptions(): iterable
    {
        yield 'passing badge without output' => [['--action' => 'generatePHPUnitPassingBadge', '--junitXMLFilePath' => 'junit.xml']];
        yield 'passing badge without junit' => [['--action' => 'generatePHPUnitPassingBadge', '--outputPassingSVGFilePath' => 'phpunit.svg']];
        yield 'tests badge without output' => [['--action' => 'generatePHPUnitTestsBadge', '--junitXMLFilePath' => 'junit.xml']];
        yield 'tests badge without junit' => [['--action' => 'generatePHPUnitTestsBadge', '--outputTestsSVGFilePath' => 'phpunit-tests.svg']];
        yield 'coverage badges without statements output' => [[
            '--action'                    => 'generatePHPUnitCodeCoverageBadge',
            '--cloverXMLFilePath'         => 'clover.xml',
            '--outputCoverageSVGFilePath' => 'coverage.svg',
        ]];
        yield 'coverage badges without clover' => [[
            '--action'                      => 'generatePHPUnitCodeCoverageBadge',
            '--outputCoverageSVGFilePath'   => 'coverage.svg',
            '--outputStatementsSVGFilePath' => 'statements.svg',
        ]];
        yield 'coverage history without history file' => [['--action' => 'generatePHPUnitCoverageHistory', '--cloverXMLFilePath' => 'clover.xml']];
        yield 'coverage history without clover' => [['--action' => 'generatePHPUnitCoverageHistory', '--historyNDJSONFilePath' => 'trend.ndjson']];
        yield 'backfill without history file' => [['--action' => 'backfillPHPUnitCoverageHistory', '--outputTrendSVGFilePath' => 'trend.svg']];
    }

    /**
     * @param array<string, string> $input
     */
    #[DataProvider('dataMissingRequiredOptions')]
    public function testActionsRequireTheirFileOptions(array $input): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing required options.');

        try {
            $this->commandTester()->execute($input);
        } finally {
            self::assertSame(['badges', 'results'], $this->listDirectory($this->projectDir));
            self::assertSame([], $this->listDirectory($this->projectDir . '/badges'));
        }
    }

    public function testGeneratePHPUnitPassingBadgeResolvesRelativePathsAgainstTheProjectDirectory(): void
    {
        // The content of the badge is tested by AuroraPHPUnitCodeCoverageBadgeTest
        file_put_contents($this->projectDir . '/results/junit.xml', $this->junitXml(12, 2, 1, 0));

        self::assertSame(PHPUnitCommand::SUCCESS, $this->commandTester()->execute([
            '--action'                   => 'generatePHPUnitPassingBadge',
            '--junitXMLFilePath'         => 'results/junit.xml',
            '--outputPassingSVGFilePath' => 'badges/phpunit.svg',
        ]));

        self::assertStringContainsString('>9 / 12</tspan>', (string)file_get_contents($this->projectDir . '/badges/phpunit.svg'));
    }

    public function testGeneratePHPUnitPassingBadgeOverwritesAnExistingBadgeGivenByAbsolutePath(): void
    {
        file_put_contents($this->projectDir . '/results/junit.xml', $this->junitXml(3, 0, 0, 0));
        file_put_contents($this->projectDir . '/badges/phpunit.svg', 'old badge');

        self::assertSame(PHPUnitCommand::SUCCESS, $this->commandTester()->execute([
            '--action'                   => 'generatePHPUnitPassingBadge',
            '--junitXMLFilePath'         => $this->projectDir . '/results/junit.xml',
            '--outputPassingSVGFilePath' => $this->projectDir . '/badges/phpunit.svg',
        ]));

        self::assertStringContainsString('>3 / 3</tspan>', (string)file_get_contents($this->projectDir . '/badges/phpunit.svg'));
        self::assertSame(['phpunit.svg'], $this->listDirectory($this->projectDir . '/badges'));
    }

    public function testGeneratePHPUnitTestsBadge(): void
    {
        file_put_contents($this->projectDir . '/results/junit.xml', $this->junitXml(10, 1, 0, 0));

        self::assertSame(PHPUnitCommand::SUCCESS, $this->commandTester()->execute([
            '--action'                 => 'generatePHPUnitTestsBadge',
            '--junitXMLFilePath'       => 'results/junit.xml',
            '--outputTestsSVGFilePath' => 'badges/phpunit-tests.svg',
        ]));

        self::assertStringContainsString('<title>PHPUnit - failing</title>', (string)file_get_contents($this->projectDir . '/badges/phpunit-tests.svg'));
    }

    public function testGeneratePHPUnitCodeCoverageBadgeWritesBothBadges(): void
    {
        file_put_contents($this->projectDir . '/results/clover.xml', $this->cloverXml(20, 17, 40, 34));

        self::assertSame(PHPUnitCommand::SUCCESS, $this->commandTester()->execute([
            '--action'                      => 'generatePHPUnitCodeCoverageBadge',
            '--cloverXMLFilePath'           => 'results/clover.xml',
            '--outputCoverageSVGFilePath'   => 'badges/coverage.svg',
            '--outputStatementsSVGFilePath' => 'badges/statements.svg',
        ]));

        self::assertStringContainsString('<text x="80" y="14" fill="#FFFFFF">85%</text>', (string)file_get_contents($this->projectDir . '/badges/coverage.svg'));
        self::assertStringContainsString('>34 / 40</text>', (string)file_get_contents($this->projectDir . '/badges/statements.svg'));
    }

    public function testGeneratePHPUnitCoverageHistorySkipsAnUnchangedEntryAndWithoutTrendPathWritesNoBadge(): void
    {
        $cloverXMLFilePath  = $this->projectDir . '/results/clover.xml';
        $historyFilePath    = $this->projectDir . '/badges/coverage-trend.ndjson';
        $historyFileContent = '{"date":"2026-01-01","sha":"abcdef0","coverage":60,"statements":5,"coveredStatements":3}' . "\n";

        file_put_contents($cloverXMLFilePath, $this->cloverXml(10, 6, 5, 3));
        file_put_contents($historyFilePath, $historyFileContent);

        $tester = $this->commandTester();

        self::assertSame(PHPUnitCommand::SUCCESS, $tester->execute([
            '--action'                => 'generatePHPUnitCoverageHistory',
            '--cloverXMLFilePath'     => $cloverXMLFilePath,
            '--historyNDJSONFilePath' => $historyFilePath,
        ]));

        self::assertStringContainsString(sprintf('Coverage history entry skipped (same values as the last entry in %s)', $historyFilePath), $tester->getDisplay());
        self::assertStringNotContainsString('Coverage trend badge', $tester->getDisplay());
        self::assertSame($historyFileContent, file_get_contents($historyFilePath));
        self::assertSame(['coverage-trend.ndjson'], $this->listDirectory($this->projectDir . '/badges'));
    }

    public function testGeneratePHPUnitCoverageHistoryKeepsMissingAbsolutePaths(): void
    {
        $historyFilePath = $this->projectDir . '/badges/coverage-trend.ndjson';
        $trendFilePath   = $this->projectDir . '/badges/coverage-trend.svg';

        file_put_contents($this->projectDir . '/results/clover.xml', $this->cloverXml(10, 6, 5, 3));

        $tester = $this->commandTester();

        self::assertSame(PHPUnitCommand::SUCCESS, $tester->execute([
            '--action'                 => 'generatePHPUnitCoverageHistory',
            '--cloverXMLFilePath'      => 'results/clover.xml',
            '--historyNDJSONFilePath'  => $historyFilePath,
            '--outputTrendSVGFilePath' => $trendFilePath,
        ]));

        self::assertStringContainsString(sprintf('Coverage history entry appended to %s', $historyFilePath), $tester->getDisplay());
        self::assertStringContainsString(sprintf('Coverage trend badge generated at %s', $trendFilePath), $tester->getDisplay());
        self::assertSame(['coverage-trend.ndjson', 'coverage-trend.svg'], $this->listDirectory($this->projectDir . '/badges'));
    }

    public function testBackfillPHPUnitCoverageHistoryRebuildsTheHistoryFromGit(): void
    {
        if (0 !== $this->runGit(['--version'])) {
            self::markTestSkipped('The "git" binary is not available.');
        }

        $this->commitCoverageBadges([[10, 20, 50], [15, 20, 75]]);

        // The existing history content is replaced
        file_put_contents($this->projectDir . '/badges/coverage-trend.ndjson', "stale\n");

        $tester = $this->commandTester();

        self::assertSame(PHPUnitCommand::SUCCESS, $tester->execute([
            '--action'                 => 'backfillPHPUnitCoverageHistory',
            '--historyNDJSONFilePath'  => 'badges/coverage-trend.ndjson',
            '--outputTrendSVGFilePath' => 'badges/coverage-trend.svg',
        ]));

        $historyFilePath = $this->projectDir . '/badges/coverage-trend.ndjson';
        self::assertStringContainsString(sprintf('Coverage history rebuilt from git with 2 entries into %s', $historyFilePath), $tester->getDisplay());
        self::assertStringContainsString(sprintf('Coverage trend badge generated at %s/badges/coverage-trend.svg', $this->projectDir), $tester->getDisplay());

        $entries = array_map(
            static fn(string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
            array_values(array_filter(explode("\n", (string)file_get_contents($historyFilePath))))
        );
        self::assertSame([[50, 20, 10], [75, 20, 15]], array_map(static fn(array $entry): array => [
            $entry['coverage'],
            $entry['statements'],
            $entry['coveredStatements'],
        ], $entries));
        self::assertStringContainsString('<polyline', (string)file_get_contents($this->projectDir . '/badges/coverage-trend.svg'));
    }

    public function testBackfillPHPUnitCoverageHistoryWithoutTrendPathWritesOnlyTheHistory(): void
    {
        if (0 !== $this->runGit(['--version'])) {
            self::markTestSkipped('The "git" binary is not available.');
        }

        $this->commitCoverageBadges([[3, 4, 75]]);

        $tester = $this->commandTester();

        self::assertSame(PHPUnitCommand::SUCCESS, $tester->execute([
            '--action'                => 'backfillPHPUnitCoverageHistory',
            '--historyNDJSONFilePath' => 'badges/coverage-trend.ndjson',
        ]));

        self::assertStringContainsString('Coverage history rebuilt from git with 1 entries', $tester->getDisplay());
        self::assertStringNotContainsString('Coverage trend badge', $tester->getDisplay());
        self::assertStringContainsString('"coverage":75,"statements":4,"coveredStatements":3', (string)file_get_contents($this->projectDir . '/badges/coverage-trend.ndjson'));
        self::assertSame(['coverage-trend.ndjson', 'coverage.svg', 'statements.svg'], $this->listDirectory($this->projectDir . '/badges'));
    }

    public function testUpdateDocumentationBlocksFailsWithoutATestsDirectory(): void
    {
        $tester = $this->commandTester();

        self::assertSame(PHPUnitCommand::FAILURE, $tester->execute(['--action' => 'updateDocumentationBlocks']));
        self::assertStringContainsString('[ERROR] Directory /test/ not found.', $tester->getDisplay());
    }

    public function testUpdateDocumentationBlocksWarnsWhenThereIsNoTestFile(): void
    {
        self::assertTrue(mkdir($this->projectDir . '/tests/Unit', 0777, true));
        file_put_contents($this->projectDir . '/tests/Unit/Helper.php', "<?php\n");

        $tester = $this->commandTester();

        self::assertSame(PHPUnitCommand::SUCCESS, $tester->execute(['--action' => 'updateDocumentationBlocks']));
        self::assertStringContainsString('[WARNING] No test files found in /test/ directory.', $tester->getDisplay());
        self::assertSame("<?php\n", file_get_contents($this->projectDir . '/tests/Unit/Helper.php'));
    }

    public function testUpdateDocumentationBlocksSkipsTestsThatDoNotExtendTheWebTestCaseMiddleware(): void
    {
        $content = <<<'PHP'
<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

class PlainTest extends TestCase
{
    public function testSomething(): void
    {
        $this->assertTrue(true);
    }
}

PHP;

        self::assertTrue(mkdir($this->projectDir . '/tests/Unit', 0777, true));
        file_put_contents($this->projectDir . '/tests/Unit/PlainTest.php', $content);

        $tester = $this->commandTester();

        self::assertSame(PHPUnitCommand::SUCCESS, $tester->execute(['--action' => 'updateDocumentationBlocks']));

        $display = preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString('The test file Unit/PlainTest.php does not contain a class that extends the WebTestCaseMiddleware.', $display);
        self::assertStringContainsString('0 class files updated, 0 class files created, 0 test methods processed.', $display);
        self::assertSame($content, file_get_contents($this->projectDir . '/tests/Unit/PlainTest.php'));
    }

    public function testUpdateDocumentationBlocksIsIdempotent(): void
    {
        self::assertTrue(mkdir($this->projectDir . '/tests/Integration', 0777, true));

        $sourceFile = $this->projectDir . '/tests/Integration/GivenTest.php';
        file_put_contents($sourceFile, (string)file_get_contents(__DIR__ . '/PHPUnit/GivenTest.php'));

        $tester = $this->commandTester();

        self::assertSame(PHPUnitCommand::SUCCESS, $tester->execute(['--action' => 'updateDocumentationBlocks']));
        self::assertStringContainsString('0 class files updated, 1 class files created, 1 test methods processed.', preg_replace('/\s+/', ' ', $tester->getDisplay()));
        $firstRun = (string)file_get_contents($sourceFile);

        // The second run replaces the class comment block and the method comment instead of stacking new ones
        self::assertSame(PHPUnitCommand::SUCCESS, $tester->execute(['--action' => 'updateDocumentationBlocks']));
        self::assertStringContainsString('1 class files updated, 0 class files created, 1 test methods processed.', preg_replace('/\s+/', ' ', $tester->getDisplay()));
        self::assertSame($firstRun, file_get_contents($sourceFile));
        self::assertSame(1, substr_count($firstRun, '--filter testCompanyConfigRelation'));
    }

    public function testUpdateDocumentationBlocksCommentsAMethodThatFollowsTheClassBrace(): void
    {
        self::assertTrue(mkdir($this->projectDir . '/tests/Api/V2', 0777, true));
        file_put_contents($this->projectDir . '/tests/Api/V2/OrderTest.php', <<<'PHP'
<?php

namespace App\Tests\Api\V2;

use Sindla\Bundle\AuroraBundle\Tests\WebTestCaseMiddleware;

class OrderTest extends WebTestCaseMiddleware
{
    public function testList(): void
    {
    }

    public function testShow(): void
    {
    }
}

PHP);

        self::assertSame(PHPUnitCommand::SUCCESS, $this->commandTester()->execute(['--action' => 'updateDocumentationBlocks']));

        $result = (string)file_get_contents($this->projectDir . '/tests/Api/V2/OrderTest.php');
        $filter = '// clear; cd /srv/${DKZ_DOMAIN}/; /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/Api/V2/OrderTest.php --no-coverage'
            . ' --do-not-cache-result --display-phpunit-notices --display-phpunit-deprecations --testdox --filter %s';

        self::assertStringContainsString(
            sprintf("{\n    %s\n    public function testList(): void\n", sprintf($filter, 'testList')),
            $result
        );
        self::assertStringContainsString(
            sprintf("    }\n\n    %s\n    public function testShow(): void\n", sprintf($filter, 'testShow')),
            $result
        );
        self::assertStringContainsString(" */\nclass OrderTest extends WebTestCaseMiddleware\n", $result);
        self::assertStringContainsString(' * clear; cd /srv/${DKZ_DOMAIN}/; SYMFONY_DEPRECATIONS_HELPER=disabled /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/Api/V2/ --no-coverage', $result);
        self::assertStringEndsWith("}\n", $result);
    }

    private function commandTester(): CommandTester
    {
        return new CommandTester(new PHPUnitCommand(new ParameterBag([
            'kernel.environment' => 'test',
            'kernel.project_dir' => $this->projectDir,
        ])));
    }

    private function junitXml(int $tests, int $failures, int $errors, int $skipped): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
    <testsuite name="Project Test Suite" tests="{$tests}" assertions="{$tests}" errors="{$errors}" failures="{$failures}" skipped="{$skipped}" time="0.5"/>
</testsuites>
XML;
    }

    private function cloverXml(int $elements, int $coveredElements, int $statements, int $coveredStatements): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
    <project>
        <metrics elements="{$elements}" coveredelements="{$coveredElements}"/>
        <file name="Sample.php">
            <metrics statements="{$statements}" coveredstatements="{$coveredStatements}"/>
        </file>
    </project>
</coverage>
XML;
    }

    /**
     * @return list<string>
     */
    private function listDirectory(string $directory): array
    {
        return array_values(array_diff((array)scandir($directory), ['.', '..']));
    }

    /**
     * Commits the statements.svg / coverage.svg badges once per data point, in a new git repository in the project directory
     *
     * @param list<array{0: int, 1: int, 2: int}> $points [coveredStatements, statements, coverage]
     */
    private function commitCoverageBadges(array $points): void
    {
        self::assertSame(0, $this->runGit(['init', '-q', '.']));

        foreach ($points as [$coveredStatements, $statements, $coverage]) {
            file_put_contents($this->projectDir . '/badges/statements.svg', sprintf('<svg><text>%d / %d</text></svg>', $coveredStatements, $statements));
            file_put_contents($this->projectDir . '/badges/coverage.svg', sprintf('<svg><text>%d%%</text></svg>', $coverage));

            self::assertSame(0, $this->runGit(['add', 'badges']));
            self::assertSame(0, $this->runGit([
                '-c', 'user.name=Aurora Tests',
                '-c', 'user.email=aurora-tests@example.com',
                '-c', 'commit.gpgsign=false',
                'commit', '-q', '-m', sprintf('Coverage %d%%', $coverage),
            ]));
        }
    }

    /**
     * @param list<string> $arguments
     */
    private function runGit(array $arguments): int
    {
        $process = @proc_open(array_merge(['git'], $arguments), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->projectDir);

        if (!is_resource($process)) {
            return 1;
        }

        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process);
    }

    private function removeDirectory(string $directory): void
    {
        if (!\is_dir($directory)) {
            return;
        }

        $items = \scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (\is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
