<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Command;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\Mapping\ClassMetadata as PersistenceClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Command\DbDiagramCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Standalone unit test for the schema exporter: the command is driven directly without booting the kernel. The generic viewer contract
 * (file output, JSON shape, schema.js wrapping, pretty/compact form, platform resolution, directory creation) is asserted with a mocked
 * EntityManager; the tables / FK relations / indexes are asserted with the real Doctrine metadata of the fixture entities declared at the
 * end of this file (the connection never connects: its server version is given).
 */
final class DbDiagramCommandTest extends TestCase
{
    private string $projectDir = '';

    /**
     * @var list<string>
     */
    private array $projectDirs = [];

    protected function tearDown(): void
    {
        // Remove the per-test project directories so repeated runs never assert against stale files.
        foreach ($this->projectDirs as $projectDir) {
            if (is_dir($projectDir)) {
                $this->removeDirectory($projectDir);
            }
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

    public function testExportsTheTablesColumnsIndexesAndRelationsOfTheEntities(): void
    {
        $this->createProjectDir();
        $outputDir = $this->projectDir . '/diagram';

        $command  = new DbDiagramCommand($this->makeFixtureEntityManager(), $this->projectDir);
        $output   = new BufferedOutput();
        $exitCode = $command(new ArrayInput([]), $output, $outputDir);

        self::assertSame(Command::SUCCESS, $exitCode);
        // An absolute --output is used as-is, not resolved against the project directory (the long path is wrapped: whitespace is ignored)
        self::assertStringContainsString(
            sprintf('[OK]Exported5tablesand5relationsto%s/{schema.json,schema.js}', $outputDir),
            preg_replace('/\s+/', '', $output->fetch())
        );

        $schema = json_decode((string)file_get_contents($outputDir . '/schema.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('postgresql', $schema['platform']);
        // Alphabetical; the mapped superclass has no table of its own
        self::assertSame(['post', 'profile', 'tag', 'user_tag', 'users'], array_column($schema['tables'], 'name'));

        $tables = array_column($schema['tables'], null, 'name');

        self::assertSame([
            'name'        => 'users',
            'entityClass' => DbDiagramFixtureUser::class,
            'isJoinTable' => false,
            'columns'     => [
                // Inherited from the mapped superclass
                ['name' => 'created_at', 'type' => 'timestamp', 'pk' => false, 'fk' => false, 'nullable' => false],
                ['name' => 'id', 'type' => 'bigint', 'pk' => true, 'fk' => false, 'nullable' => false],
                ['name' => 'email', 'type' => 'varchar(180)', 'pk' => false, 'fk' => false, 'nullable' => false],
                ['name' => 'nickname', 'type' => 'varchar', 'pk' => false, 'fk' => false, 'nullable' => true],
                ['name' => 'order', 'type' => 'int', 'pk' => false, 'fk' => false, 'nullable' => false],
                ['name' => 'uuid', 'type' => 'uuid', 'pk' => false, 'fk' => false, 'nullable' => false],
                ['name' => 'balance', 'type' => 'numeric(12,2)', 'pk' => false, 'fk' => false, 'nullable' => false],
                ['name' => 'rating', 'type' => 'numeric(10,0)', 'pk' => false, 'fk' => false, 'nullable' => true],
                ['name' => 'settings', 'type' => 'jsonb', 'pk' => false, 'fk' => false, 'nullable' => false],
                // A type unknown to the label map is exported verbatim
                ['name' => 'score', 'type' => 'smallfloat', 'pk' => false, 'fk' => false, 'nullable' => false],
            ],
            'indexes'     => [
                ['name' => 'idx_users_email', 'columns' => ['email'], 'unique' => false],
                ['name' => 'idx_users_nickname', 'columns' => ['nickname', 'email'], 'unique' => false],
                ['name' => 'uniq_users_uuid', 'columns' => ['uuid'], 'unique' => true],
            ],
        ], $tables['users']);

        // The type of an FK column that references a derived identity (the PK of the profile is a relation, not a field) is not resolved
        // through the relation: it is not asserted
        unset($tables['post']['columns'][2]['type']);

        self::assertSame([
            'name'        => 'post',
            'entityClass' => DbDiagramFixturePost::class,
            'isJoinTable' => false,
            'columns'     => [
                ['name' => 'id', 'type' => 'int', 'pk' => true, 'fk' => false, 'nullable' => false],
                // The FK column has the type of the referenced primary key
                ['name' => 'author_id', 'type' => 'bigint', 'pk' => false, 'fk' => true, 'nullable' => true],
                ['name' => 'reviewer_id', 'pk' => false, 'fk' => true, 'nullable' => false],
            ],
            'indexes'     => [],
        ], $tables['post']);

        // Derived identity: the FK column is the primary key
        self::assertSame(
            [['name' => 'user_id', 'type' => 'bigint', 'pk' => true, 'fk' => true, 'nullable' => false]],
            $tables['profile']['columns']
        );

        self::assertSame([
            'name'        => 'user_tag',
            'entityClass' => null,
            'isJoinTable' => true,
            'columns'     => [
                ['name' => 'user_id', 'type' => 'bigint', 'pk' => true, 'fk' => true, 'nullable' => false],
                ['name' => 'tag_code', 'type' => 'varchar(32)', 'pk' => true, 'fk' => true, 'nullable' => false],
            ],
            'indexes'     => [],
        ], $tables['user_tag']);

        self::assertSame([['name' => 'code', 'type' => 'varchar(32)', 'pk' => true, 'fk' => false, 'nullable' => false]], $tables['tag']['columns']);

        // Only the owning sides, sorted by their source endpoint
        self::assertSame([
            [
                'fromTable'  => 'post',
                'fromColumn' => 'author_id',
                'toTable'    => 'users',
                'toColumn'   => 'id',
                'type'       => 'many-to-one',
                'field'      => 'author',
                'onDelete'   => 'SET NULL',
            ],
            [
                'fromTable'  => 'post',
                'fromColumn' => 'reviewer_id',
                'toTable'    => 'profile',
                'toColumn'   => 'user_id',
                'type'       => 'many-to-one',
                'field'      => 'reviewer',
                'onDelete'   => null,
            ],
            [
                'fromTable'  => 'profile',
                'fromColumn' => 'user_id',
                'toTable'    => 'users',
                'toColumn'   => 'id',
                'type'       => 'one-to-one',
                'field'      => 'user',
                'onDelete'   => 'CASCADE',
            ],
            [
                'fromTable'  => 'user_tag',
                'fromColumn' => 'tag_code',
                'toTable'    => 'tag',
                'toColumn'   => 'code',
                'type'       => 'many-to-many',
                'field'      => 'tags',
                'onDelete'   => null,
            ],
            [
                'fromTable'  => 'user_tag',
                'fromColumn' => 'user_id',
                'toTable'    => 'users',
                'toColumn'   => 'id',
                'type'       => 'many-to-many',
                'field'      => 'tags',
                'onDelete'   => 'CASCADE',
            ],
        ], $schema['relations']);
    }

    public function testFailsWhenTheOutputDirectoryCannotBeCreated(): void
    {
        $this->createProjectDir();
        file_put_contents($this->projectDir . '/file', '');

        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;

            return true;
        }, E_WARNING);

        $command = new DbDiagramCommand($this->makeEntityManager('pdo_pgsql'), $this->projectDir);
        $output  = new BufferedOutput();

        try {
            $exitCode = $command(new ArrayInput([]), $output, $this->projectDir . '/file/out');
        } finally {
            restore_error_handler();
        }

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame(['mkdir(): Not a directory'], $warnings);
        self::assertStringContainsString(
            sprintf('[ERROR]Cannotcreatetheoutputdirectory"%s/file/out".', $this->projectDir),
            preg_replace('/\s+/', '', $output->fetch())
        );
        self::assertSame(['file'], array_values(array_diff((array)scandir($this->projectDir), ['.', '..'])));
    }

    /**
     * Runs the command against an empty (mocked) metadata set and returns [exitCode, display, resolvedOutputDir].
     *
     * @return array{0: int, 1: string, 2: string}
     */
    private function runCommand(string $driver = 'pdo_pgsql', bool $pretty = true, string $relativeOutput = 'out'): array
    {
        $this->createProjectDir();

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

    private function createProjectDir(): void
    {
        $this->projectDir    = sys_get_temp_dir() . '/aurora_dbdiagram_' . uniqid('', true);
        $this->projectDirs[] = $this->projectDir;
        self::assertTrue(mkdir($this->projectDir));
    }

    /**
     * A real EntityManager whose metadata driver lists the fixture entities of this file (an AttributeDriver without paths cannot list them)
     */
    private function makeFixtureEntityManager(): EntityManagerInterface
    {
        $config = method_exists(ORMSetup::class, 'createAttributeMetadataConfig')
            ? ORMSetup::createAttributeMetadataConfig([], true)
            : ORMSetup::createAttributeMetadataConfiguration([], true);

        if (PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        $config->setMetadataDriverImpl(new class ([
            DbDiagramFixtureTag::class,
            DbDiagramFixtureTimestamped::class,
            DbDiagramFixtureUser::class,
            DbDiagramFixtureProfile::class,
            DbDiagramFixturePost::class,
        ]) implements MappingDriver {
            private readonly AttributeDriver $attributeDriver;

            /**
             * @param list<class-string> $classNames
             */
            public function __construct(
                private readonly array $classNames,
            )
            {
                $this->attributeDriver = new AttributeDriver([]);
            }

            public function loadMetadataForClass(string $className, PersistenceClassMetadata $metadata): void
            {
                $this->attributeDriver->loadMetadataForClass($className, $metadata);
            }

            public function getAllClassNames(): array
            {
                return $this->classNames;
            }

            public function isTransient(string $className): bool
            {
                return $this->attributeDriver->isTransient($className);
            }
        });

        return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_pgsql', 'serverVersion' => '16.0'], $config), $config);
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

#[ORM\MappedSuperclass]
abstract class DbDiagramFixtureTimestamped
{
    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    protected \DateTimeImmutable $createdAt;
}

#[ORM\Entity]
#[ORM\Table(name: '`users`')]
#[ORM\Index(name: 'idx_users_email', columns: ['email'])]
#[ORM\Index(name: 'idx_users_nickname', columns: ['nickname', 'email'], flags: ['fulltext'])]
#[ORM\UniqueConstraint(name: 'uniq_users_uuid', columns: ['uuid'])]
class DbDiagramFixtureUser extends DbDiagramFixtureTimestamped
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\Column(type: 'string', length: 180)]
    private string $email;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $nickname = null;

    #[ORM\Column(name: '`order`', type: 'integer')]
    private int $order;

    #[ORM\Column(type: 'guid')]
    private string $uuid;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $balance;

    #[ORM\Column(type: 'decimal', nullable: true)]
    private ?string $rating = null;

    #[ORM\Column(type: 'json')]
    private array $settings;

    #[ORM\Column(type: 'smallfloat')]
    private float $score;

    #[ORM\OneToMany(targetEntity: DbDiagramFixturePost::class, mappedBy: 'author')]
    private Collection $posts;

    #[ORM\ManyToMany(targetEntity: DbDiagramFixtureTag::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'user_tag')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'tag_code', referencedColumnName: 'code')]
    private Collection $tags;
}

#[ORM\Entity]
#[ORM\Table(name: 'profile')]
class DbDiagramFixtureProfile
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: DbDiagramFixtureUser::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private DbDiagramFixtureUser $user;
}

#[ORM\Entity]
#[ORM\Table(name: 'post')]
class DbDiagramFixturePost
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DbDiagramFixtureUser::class, inversedBy: 'posts')]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?DbDiagramFixtureUser $author = null;

    // The primary key of the profile is itself a relation: the referenced column is not a field of the profile
    #[ORM\ManyToOne(targetEntity: DbDiagramFixtureProfile::class)]
    #[ORM\JoinColumn(name: 'reviewer_id', referencedColumnName: 'user_id', nullable: false)]
    private DbDiagramFixtureProfile $reviewer;
}

#[ORM\Entity]
#[ORM\Table(name: 'tag')]
class DbDiagramFixtureTag
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $code;

    #[ORM\ManyToMany(targetEntity: DbDiagramFixtureUser::class, mappedBy: 'tags')]
    private Collection $users;
}
