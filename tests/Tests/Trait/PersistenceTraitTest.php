<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Tests\Trait;

use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Tests\Trait\PersistenceTrait;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PersistenceTraitTest extends TestCase
{
    private bool $keepStaticConnections;

    protected function setUp(): void
    {
        $this->keepStaticConnections = StaticDriver::isKeepStaticConnections();
    }

    protected function tearDown(): void
    {
        StaticDriver::setKeepStaticConnections($this->keepStaticConnections);
    }

    /**
     * @param list<bool> $arguments
     */
    #[DataProvider('dataStateMethods')]
    public function testTheStateMethodsReturnThePreviousState(
        string $method,
        array $arguments,
        bool $initialState,
        bool $expectedReturn,
        bool $expectedState,
    ): void {
        StaticDriver::setKeepStaticConnections($initialState);

        $this->assertSame($expectedReturn, $this->createSubject()->call($method, ...$arguments));
        $this->assertSame($expectedState, StaticDriver::isKeepStaticConnections());
    }

    public static function dataStateMethods(): iterable
    {
        yield 'disable when enabled' => ['disableDAMA', [], true, true, false];
        yield 'disable when disabled' => ['disableDAMA', [], false, false, false];
        yield 'enable when disabled' => ['enableDAMA', [], false, false, true];
        yield 'enable when enabled' => ['enableDAMA', [], true, true, true];
        yield 'set to disabled' => ['setDAMAState', [false], true, true, false];
        yield 'set to enabled' => ['setDAMAState', [true], false, false, true];
        yield 'is enabled' => ['isDAMAEnabled', [], true, true, true];
        yield 'is disabled' => ['isDAMAEnabled', [], false, false, false];
    }

    #[DataProvider('dataCallbackMethods')]
    public function testTheCallbackRunsInTheRequestedStateThenTheStateIsRestored(string $method, bool $initialState, bool $expectedStateInside): void
    {
        StaticDriver::setKeepStaticConnections($initialState);

        $stateInside = $this->createSubject()->call($method, static fn(): bool => StaticDriver::isKeepStaticConnections());

        $this->assertSame($expectedStateInside, $stateInside);
        $this->assertSame($initialState, StaticDriver::isKeepStaticConnections());
    }

    public static function dataCallbackMethods(): iterable
    {
        yield 'without DAMA, when enabled' => ['withoutDAMA', true, false];
        yield 'without DAMA, when disabled' => ['withoutDAMA', false, false];
        yield 'with DAMA, when disabled' => ['withDAMA', false, true];
        yield 'with DAMA, when enabled' => ['withDAMA', true, true];
    }

    #[DataProvider('dataCallbackMethods')]
    public function testTheStateIsRestoredWhenTheCallbackThrows(string $method, bool $initialState, bool $expectedStateInside): void
    {
        StaticDriver::setKeepStaticConnections($initialState);
        $stateInside = null;

        try {
            $this->createSubject()->call($method, static function () use (&$stateInside): never {
                $stateInside = StaticDriver::isKeepStaticConnections();

                throw new \LogicException('The callback failed.');
            });
            $this->fail('The exception of the callback was not rethrown.');
        } catch (\LogicException $exception) {
            $this->assertSame('The callback failed.', $exception->getMessage());
        }

        $this->assertSame($expectedStateInside, $stateInside);
        $this->assertSame($initialState, StaticDriver::isKeepStaticConnections());
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testFindPersistedEntityUsesTheEntityManagerOfTheContainer(): void
    {
        [$em, $subject] = $this->createDatabase();
        $managed        = $em->getRepository(PersistenceTraitItem::class)->findOneBy(['name' => 'b']);

        $found = $subject->call('findPersistedEntity', PersistenceTraitItem::class, ['name' => 'b']);

        // Read from the database by another entity manager, not from the identity map of the test entity manager
        $this->assertInstanceOf(PersistenceTraitItem::class, $found);
        $this->assertNotSame($managed, $found);
        $this->assertSame(2, $found->quantity);
        $this->assertNull($subject->call('findPersistedEntity', PersistenceTraitItem::class, ['name' => 'z']));
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testFindPersistedEntities(): void
    {
        [, $subject] = $this->createDatabase();

        $this->assertSame(['b', 'c'], $this->names($subject->call('findPersistedEntities', PersistenceTraitItem::class, ['quantity' => [2, 3]], ['name' => 'ASC'])));
        $this->assertSame(['b', 'a'], $this->names($subject->call('findPersistedEntities', PersistenceTraitItem::class, [], ['quantity' => 'DESC'], 2, 1)));
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testCountPersistedEntities(): void
    {
        [, $subject] = $this->createDatabase();

        $this->assertSame(3, $subject->call('countPersistedEntities', PersistenceTraitItem::class));
        $this->assertSame(2, $subject->call('countPersistedEntities', PersistenceTraitItem::class, ['quantity' => [1, 3]]));
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testClearPersistedEntities(): void
    {
        [$em, $subject] = $this->createDatabase();
        StaticDriver::setKeepStaticConnections(true);

        $subject->call('clearPersistedEntities', [PersistenceTraitItem::class]);

        $this->assertSame(0, (int)$em->getConnection()->fetchOne('SELECT COUNT(*) FROM persistence_trait_item'));
        $this->assertTrue(StaticDriver::isKeepStaticConnections());
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testClearPersistedEntitiesByCriteria(): void
    {
        [$em, $subject] = $this->createDatabase();
        StaticDriver::setKeepStaticConnections(true);

        $subject->call('clearPersistedEntitiesByCriteria', PersistenceTraitItem::class, ['quantity' => [1, 3]]);

        $this->assertSame(['b'], $em->getConnection()->fetchFirstColumn('SELECT name FROM persistence_trait_item'));
        $this->assertTrue(StaticDriver::isKeepStaticConnections());
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testExecutePersistedQuery(): void
    {
        [, $subject] = $this->createDatabase();

        $this->assertSame(
            [['name' => 'b'], ['name' => 'c']],
            $subject->call('executePersistedQuery', 'SELECT name FROM persistence_trait_item WHERE quantity > ? ORDER BY name', [1])
        );
    }

    private function createSubject(?EntityManagerInterface $em = null, ?ContainerInterface $container = null): object
    {
        return new class ($em, $container ?? new Container()) {
            use PersistenceTrait;

            public function __construct(
                public ?EntityManagerInterface $em,
                private readonly ContainerInterface $container,
            ) {
            }

            public function call(string $method, mixed ...$arguments): mixed
            {
                return $this->{$method}(...$arguments);
            }

            protected function getContainer(): ContainerInterface
            {
                return $this->container;
            }
        };
    }

    /**
     * The test entity manager and a subject whose container returns another entity manager of the same database
     *
     * @return array{0: EntityManager, 1: object}
     */
    private function createDatabase(): array
    {
        $config = ORMSetup::createAttributeMetadataConfig([], true);
        $config->enableNativeLazyObjects(true);

        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        new SchemaTool($em)->createSchema([$em->getClassMetadata(PersistenceTraitItem::class)]);

        foreach (['a' => 1, 'b' => 2, 'c' => 3] as $name => $quantity) {
            $em->persist(new PersistenceTraitItem($name, $quantity));
        }

        $em->flush();

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn(new EntityManager($em->getConnection(), $config));

        $container = new Container();
        $container->set('doctrine', $registry);

        return [$em, $this->createSubject($em, $container)];
    }

    /**
     * @param list<PersistenceTraitItem> $items
     *
     * @return list<string>
     */
    private function names(array $items): array
    {
        return array_map(static fn(PersistenceTraitItem $item): string => $item->name, $items);
    }
}

#[ORM\Entity]
#[ORM\Table(name: 'persistence_trait_item')]
class PersistenceTraitItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 20)]
        public string $name,
        #[ORM\Column(type: Types::INTEGER)]
        public int    $quantity,
    ) {
    }
}
