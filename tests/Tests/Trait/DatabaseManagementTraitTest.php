<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Tests\Trait;

use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Tests\Trait\DatabaseManagementTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelInterface;

class DatabaseManagementTraitTest extends TestCase
{
    private bool $keepStaticConnections;

    /**
     * @var \ArrayObject<int, array{0: string, 1: bool}> The command lines run by the console, with the DAMA state during the run
     */
    private \ArrayObject $commands;

    protected function setUp(): void
    {
        // The DAMA PHPUnit extension keeps the static connections during the tests
        $this->keepStaticConnections = StaticDriver::isKeepStaticConnections();
        StaticDriver::setKeepStaticConnections(true);

        $this->commands = new \ArrayObject();
    }

    protected function tearDown(): void
    {
        StaticDriver::setKeepStaticConnections($this->keepStaticConnections);
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    #[DataProvider('dataTruncateTable')]
    public function testTruncateTable(string $table, int $expectedGadgets, int $expectedWidgets): void
    {
        $em         = $this->createEntityManager();
        $connection = $em->getConnection();
        $connection->executeStatement('CREATE TABLE widget (id INTEGER)');
        $connection->executeStatement('INSERT INTO widget (id) VALUES (1)');
        $connection->executeStatement("INSERT INTO gadget (name) VALUES ('lamp')");

        $this->createSubject($em)->truncateTable($table);

        $this->assertSame($expectedGadgets, (int)$connection->fetchOne('SELECT COUNT(*) FROM gadget'));
        $this->assertSame($expectedWidgets, (int)$connection->fetchOne('SELECT COUNT(*) FROM widget'));
        $this->assertTrue(StaticDriver::isKeepStaticConnections());
    }

    public static function dataTruncateTable(): iterable
    {
        yield 'entity class of the host application' => ['App\Entity\Gadget', 0, 1];
        yield 'table name' => ['widget', 1, 0];
    }

    public function testTruncateTablePassesTheCascadeOption(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
        $connection->expects($this->once())->method('executeStatement')->with('TRUNCATE gadget CASCADE');

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        $this->createSubject($em)->truncateTable('App\Entity\Gadget', true);
    }

    public function testDbResetWithoutFixturesRecreatesTheDatabaseWithoutTheStaticConnections(): void
    {
        $this->createSubject()->dbResetWithoutFixtures();

        $this->assertSame([
            ['doctrine:database:drop --if-exists --force', false],
            ['doctrine:database:create --quiet', false],
            ['doctrine:migrations:migrate --no-interaction', false],
        ], $this->commands->getArrayCopy());
        $this->assertTrue(StaticDriver::isKeepStaticConnections());
    }

    public function testDbResetWithFixturesAlsoAppendsTheFixtures(): void
    {
        $this->createSubject()->dbResetWithFixtures();

        $this->assertSame([
            ['doctrine:database:drop --if-exists --force', false],
            ['doctrine:database:create --quiet', false],
            ['doctrine:migrations:migrate --no-interaction', false],
            ['doctrine:fixtures:load --append --no-interaction', false],
        ], $this->commands->getArrayCopy());
        $this->assertTrue(StaticDriver::isKeepStaticConnections());
    }

    /**
     * @param list<string> $groups
     */
    #[DataProvider('dataLoadFixtures')]
    public function testLoadFixtures(bool $append, bool $verbose, array $groups, string $expectedCommand, string $expectedOutput): void
    {
        $output = $this->createSubject()->fixtures($append, $verbose, $groups);

        $this->assertSame([[$expectedCommand, true]], $this->commands->getArrayCopy());
        $this->assertSame($expectedOutput, $output);
    }

    public static function dataLoadFixtures(): iterable
    {
        yield 'purge the database' => [false, false, [], 'doctrine:fixtures:load --no-interaction', ''];
        yield 'verbose, only some groups' => [
            true,
            true,
            ['countries', 'users'],
            'doctrine:fixtures:load --append --group=countries,users --no-interaction --verbose',
            "doctrine:fixtures:load done\n",
        ];
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testSaveAndRefresh(): void
    {
        $em      = $this->createEntityManager();
        $subject = $this->createSubject($em);
        $gadget  = new DatabaseManagementTraitGadget('lamp');

        $subject->save($gadget);

        $this->assertNotNull($gadget->id);
        $this->assertSame('lamp', $em->getConnection()->fetchOne('SELECT name FROM gadget WHERE id = ?', [$gadget->id]));

        $em->getConnection()->executeStatement('UPDATE gadget SET name = ? WHERE id = ?', ['desk', $gadget->id]);
        $subject->refresh($gadget);

        $this->assertSame('desk', $gadget->name);
    }

    private function createSubject(?EntityManagerInterface $em = null): object
    {
        $subject = new class {
            use DatabaseManagementTrait;

            public static ?KernelInterface $kernel = null;

            public ?EntityManagerInterface $em = null;

            protected static function bootKernel(): KernelInterface
            {
                return self::$kernel;
            }

            /**
             * @param list<string> $groups
             */
            public function fixtures(bool $append, bool $verbose, array $groups): string
            {
                return $this->loadFixtures($append, $verbose, $groups);
            }
        };

        $subject->em = $em;
        $subject::$kernel = $this->createKernel();

        return $subject;
    }

    /**
     * A kernel whose console only knows the Doctrine commands the trait runs: they record their options
     */
    private function createKernel(): KernelInterface
    {
        $commands  = $this->commands;
        $factories = [];

        $definitions = [
            'doctrine:database:drop'      => ['if-exists' => InputOption::VALUE_NONE, 'force' => InputOption::VALUE_NONE],
            'doctrine:database:create'    => [],
            'doctrine:migrations:migrate' => [],
            'doctrine:fixtures:load'      => ['append' => InputOption::VALUE_NONE, 'group' => InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY],
        ];

        foreach ($definitions as $name => $options) {
            $factories[$name] = static function () use ($name, $options, $commands): Command {
                $command = new Command($name);

                foreach ($options as $option => $mode) {
                    $command->addOption($option, null, $mode);
                }

                return $command->setCode(static function (InputInterface $input, OutputInterface $output) use ($name, $commands): int {
                    $flags = [];

                    foreach (['if-exists', 'force', 'append', 'group', 'quiet', 'no-interaction', 'verbose'] as $option) {
                        $value = $input->hasOption($option) ? $input->getOption($option) : null;

                        if (true === $value) {
                            $flags[] = sprintf('--%s', $option);
                        } elseif (is_array($value) && [] !== $value) {
                            $flags[] = sprintf('--%s=%s', $option, implode(',', $value));
                        }
                    }

                    $commands[] = [implode(' ', [$name, ...$flags]), StaticDriver::isKeepStaticConnections()];
                    $output->writeln(sprintf('%s done', $name));

                    return Command::SUCCESS;
                });
            };
        }

        $container = new Container();
        $container->set('event_dispatcher', new EventDispatcher());
        $container->set('console.command_loader', new FactoryCommandLoader($factories));

        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getContainer')->willReturn($container);

        return $kernel;
    }

    private function createEntityManager(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfig([], true);
        $config->enableNativeLazyObjects(true);

        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        new SchemaTool($em)->createSchema([$em->getClassMetadata(DatabaseManagementTraitGadget::class)]);

        return $em;
    }
}

#[ORM\Entity]
#[ORM\Table(name: 'gadget')]
class DatabaseManagementTraitGadget
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 20)]
        public string $name,
    ) {
    }
}
