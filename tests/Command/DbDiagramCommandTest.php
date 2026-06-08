<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Command;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Command\DbDiagramCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Standalone unit test for the schema exporter: the command is driven directly with a mocked EntityManager, so it asserts on the
 * generic viewer contract (file output, JSON shape, schema.js wrapping, pretty/compact form, platform resolution, directory creation)
 * without booting the kernel or depending on any host-application entity. The FK / index mapping that depends on concrete entities is
 * exercised in the downstream project that owns those entities, not in the bundle.
 */
final class DbDiagramCommandTest extends TestCase
{
    private string $projectDir = '';

    protected function tearDown(): void
    {
        // Remove the per-test project directory so repeated runs never assert against stale files.
        if ('' !== $this->projectDir && is_dir($this->projectDir)) {
            $this->removeDirectory($this->projectDir);
        }

        parent::tearDown();
    }

    public function testWritesBothSchemaFilesAndReturnsSuccess(): void
    {
        [$exitCode, $display, $outputDir] = $this->runCommand();

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Exported', $display);
        self::assertFileExists($outputDir . '/schema.json');
        self::assertFileExists($outputDir . '/schema.js');
    }

    public function testSchemaHasTheExpectedShapeForAnEmptyMetadataSet(): void
    {
        [, , $outputDir] = $this->runCommand('pdo_pgsql');

        $schema = json_decode((string)file_get_contents($outputDir . '/schema.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('generatedAt', $schema);
        self::assertSame('postgresql', $schema['platform']);
        self::assertSame([], $schema['tables']);
        self::assertSame([], $schema['relations']);
    }

    public function testSchemaJsWrapsSchemaJsonAsAWindowGlobal(): void
    {
        [, , $outputDir] = $this->runCommand();

        $json = rtrim((string)file_get_contents($outputDir . '/schema.json'), "\n");
        $js   = (string)file_get_contents($outputDir . '/schema.js');

        // The viewer contract: schema.js carries the exact same payload as schema.json, assigned to window.DB_SCHEMA (fetch() is CORS-blocked under file://, a <script src> is not).
        self::assertSame('window.DB_SCHEMA = ' . $json . ";\n", $js);
    }

    public function testNoPrettyProducesCompactJson(): void
    {
        [, , $prettyDir]  = $this->runCommand('pdo_pgsql', true);
        [, , $compactDir] = $this->runCommand('pdo_pgsql', false);

        $prettyBody  = rtrim((string)file_get_contents($prettyDir . '/schema.json'), "\n");
        $compactBody = rtrim((string)file_get_contents($compactDir . '/schema.json'), "\n");

        // Pretty form is indented (multi-line); compact form is a single line. Both decode to the same payload.
        self::assertStringContainsString("\n", $prettyBody);
        self::assertStringNotContainsString("\n", $compactBody);
        self::assertSame(
            json_decode($prettyBody, true, 512, JSON_THROW_ON_ERROR),
            json_decode($compactBody, true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testCreatesTheOutputDirectoryWhenMissing(): void
    {
        [$exitCode, , $outputDir] = $this->runCommand('pdo_pgsql', true, 'nested/sub/dir');

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertDirectoryExists($outputDir);
        self::assertFileExists($outputDir . '/schema.json');
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function platformProvider(): iterable
    {
        yield 'postgresql' => ['pdo_pgsql', 'postgresql'];
        yield 'mysql'      => ['pdo_mysql', 'mysql'];
        yield 'sqlite'     => ['pdo_sqlite', 'sqlite'];
        yield 'unknown'    => ['', 'unknown'];
        yield 'verbatim'   => ['oci8', 'oci8'];
    }

    #[DataProvider('platformProvider')]
    public function testPlatformResolutionMapsTheDriver(string $driver, string $expectedPlatform): void
    {
        [, , $outputDir] = $this->runCommand($driver);

        $schema = json_decode((string)file_get_contents($outputDir . '/schema.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($expectedPlatform, $schema['platform']);
    }

    /**
     * Runs the command against an empty (mocked) metadata set and returns [exitCode, display, resolvedOutputDir].
     *
     * @return array{0: int, 1: string, 2: string}
     */
    private function runCommand(string $driver = 'pdo_pgsql', bool $pretty = true, string $relativeOutput = 'out'): array
    {
        $this->projectDir = sys_get_temp_dir() . '/aurora_dbdiagram_' . uniqid('', true);
        self::assertTrue(mkdir($this->projectDir));

        $command = new DbDiagramCommand($this->makeEntityManager($driver), $this->projectDir);

        $output   = new BufferedOutput();
        $exitCode = $command(new ArrayInput([]), $output, $relativeOutput, $pretty);

        // The command resolves a non-"/"-prefixed --output against the project directory.
        return [$exitCode, $output->fetch(), $this->projectDir . '/' . rtrim($relativeOutput, '/')];
    }

    private function makeEntityManager(string $driver): EntityManagerInterface
    {
        // Pure return-value stubs (no interaction expectations): createStub() is the PHPUnit 12 idiom and avoids the
        // "no expectations configured for the mock object" notice that createMock() raises for stub-only doubles.
        $metadataFactory = $this->createStub(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([]);

        $connection = $this->createStub(Connection::class);
        $connection->method('getParams')->willReturn('' === $driver ? [] : ['driver' => $driver]);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getMetadataFactory')->willReturn($metadataFactory);
        $entityManager->method('getConnection')->willReturn($connection);

        return $entityManager;
    }

    private function removeDirectory(string $directory): void
    {
        $items = scandir($directory);
        if (false === $items) {
            return;
        }

        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
