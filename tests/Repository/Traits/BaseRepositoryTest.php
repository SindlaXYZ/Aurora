<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Repository\Traits;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Repository\Traits\BaseRepository;
use Symfony\Component\Serializer\Attribute\Groups;

#[RequiresPhpExtension('pdo_sqlite')]
class BaseRepositoryTest extends TestCase
{
    private EntityManager $em;

    private BaseRepositoryItemRepository $repository;

    protected function setUp(): void
    {
        $config = method_exists(ORMSetup::class, 'createAttributeMetadataConfig')
            ? ORMSetup::createAttributeMetadataConfig([], true)
            : ORMSetup::createAttributeMetadataConfiguration([], true);

        if (PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        $this->em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        new SchemaTool($this->em)->createSchema([$this->em->getClassMetadata(BaseRepositoryItem::class)]);

        foreach (['a' => 1, 'b' => 2, 'c' => 3] as $name => $quantity) {
            $this->em->persist(new BaseRepositoryItem($name, $quantity));
        }

        $this->em->flush();
        $this->em->clear();

        $this->repository = $this->em->getRepository(BaseRepositoryItem::class);
    }

    /**
     * @param array<string, list<array{0: string, 1: string, 2: mixed}>> $where
     * @param list<string>                                               $expectedNames
     */
    #[DataProvider('whereProvider')]
    public function testGetResultsFiltersByTheWhereConditions(array $where, array $expectedNames): void
    {
        $this->assertSame($expectedNames, $this->names($this->repository->setWhere($where)->setOrder(['name' => 'ASC'])->getResults()));
    }

    public static function whereProvider(): iterable
    {
        // "#[ORM\Column]" without an explicit "type" used to be an "Undefined array key" error, and the condition was added once per attribute
        yield 'column without an explicit type and with two attributes' => [['AND' => [['name', '=', 'b']]], ['b']];
        // The conditions of an "OR" group used to be joined with AND
        yield 'OR group' => [['OR' => [['quantity', '=', 1], ['quantity', '=', 3]]], ['a', 'c']];
        yield 'OR group and AND group' => [['OR' => [['quantity', '=', 1], ['quantity', '=', 2]], 'AND' => [['name', '!=', 'a']]], ['b']];
        yield 'IN' => [['AND' => [['quantity', 'in', [2, 3]]]], ['b', 'c']];
        yield 'NOT IN' => [['AND' => [['quantity', 'NOT IN', [2, 3]]]], ['a']];
        yield 'LIKE' => [['AND' => [['name', 'LIKE', 'c%']]], ['c']];
        yield 'IS NULL' => [['AND' => [['name', '=', null]]], []];
        yield 'IS NOT NULL' => [['AND' => [['name', '!=', null]]], ['a', 'b', 'c']];
        yield 'field of an embeddable' => [['AND' => [['address.city', '=', 'city-b']]], ['b']];
    }

    public function testGetResultsOrdersByEveryColumn(): void
    {
        $this->em->persist(new BaseRepositoryItem('d', 3));
        $this->em->flush();

        // orderBy() used to replace the previous ORDER BY: only the last column was used
        $results = $this->repository->setOrder(['quantity' => 'DESC', 'name' => 'DESC'])->getResults();

        $this->assertSame(['d', 'c', 'b', 'a'], $this->names($results));
    }

    public function testGetResultsNumberAndGetResult(): void
    {
        $this->assertSame(1, (int)$this->repository->setWhere(['AND' => [['quantity', '>', 2]]])->getResultsNumber());
        $this->assertSame('c', $this->repository->getResult()?->name);
    }

    public function testGetResultsNumberIgnoresThePaginationAndTheOrder(): void
    {
        $this->repository->setOrder(['name' => 'DESC'])->setLimit(2, 2);

        // The offset used to be applied to the COUNT() query too: NoResultException
        $this->assertSame(3, (int)$this->repository->getResultsNumber());
        $this->assertSame(['a'], $this->names($this->repository->getResults()));
    }

    /**
     * @param array<string, list<array{0: string, 1: string, 2: mixed}>> $where
     * @param array<string, string>                                      $orders
     */
    #[DataProvider('invalidQueryProvider')]
    public function testColumnsOperatorsAndDirectionsAreNotWrittenAsTheyAreIntoTheDql(array $where, array $orders, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->repository->setWhere($where)->setOrder($orders)->getResults();
    }

    public static function invalidQueryProvider(): iterable
    {
        yield 'column' => [['AND' => [['quantity = 1 OR 1', '=', 1]]], [], 'Invalid column "quantity = 1 OR 1"'];
        yield 'operator' => [['AND' => [['quantity', '= 1 OR 1 =', 1]]], [], 'Invalid operator "= 1 OR 1 ="'];
        yield 'order column' => [[], ['name, id' => 'ASC'], 'Invalid order column "name, id"'];
        yield 'order direction' => [[], ['name' => 'ASC, id DESC'], 'Invalid order direction "ASC, ID DESC"'];
    }

    public function testApplyFiltersRejectsAFieldThatIsNotAnIdentifier(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repository->findAllQueryBuilder([['operator' => 'EQ', 'field' => 'name = name OR 1', 'value' => 'a']]);
    }

    /**
     * @param list<array<string, mixed>> $filters
     * @param list<string>               $expectedNames
     */
    #[DataProvider('dataApplyFilters')]
    public function testApplyFilters(array $filters, array $expectedNames): void
    {
        $items = $this->repository->findAllQueryBuilder($filters)->orderBy('alias.id')->getQuery()->getResult();

        $this->assertSame($expectedNames, $this->names($items));
    }

    public static function dataApplyFilters(): iterable
    {
        // Two filters on the same field and operator used to share one parameter: the value of the last filter was used for both
        yield 'same field and operator' => [
            [['operator' => 'GT', 'field' => 'quantity', 'value' => 2], ['operator' => 'GT', 'field' => 'quantity', 'value' => 0]],
            ['c'],
        ];
        yield 'a bounded interval' => [
            [['operator' => 'GTE', 'field' => 'quantity', 'value' => 2], ['operator' => 'LTE', 'field' => 'quantity', 'value' => 2]],
            ['b'],
        ];
        // "Gt" used to be ignored: every row was returned
        yield 'case-insensitive operator' => [[['operator' => 'Gt', 'field' => 'quantity', 'value' => 1]], ['b', 'c']];
        // UNACCENT() is a PostgreSQL function: the query failed on the other platforms
        yield 'is null' => [[['operator' => 'ISNULL', 'field' => 'name']], []];
        yield 'is not null' => [[['operator' => 'notnull', 'field' => 'quantity']], ['a', 'b', 'c']];
        // The keys of the list were written into the DQL (in the parameter names), the keys of the pairs had to be 0 and 1
        yield 'ranges with keys' => [
            [['operator' => 'RANGE', 'field' => 'quantity', 'value' => ['low' => ['from' => 0, 'to' => 2], 'high' => [3, 4]]]],
            ['a', 'c'],
        ];
    }

    public function testApplyFiltersRewritesTheZoneOnlyWhenTheQueryJoinsIt(): void
    {
        $filters = [['operator' => 'IN', 'field' => 'zone', 'value' => [1]], ['operator' => 'EQ', 'field' => 'zoneId', 'value' => 1]];

        // Without the "zone" alias, "zoneId" was rewritten to "zone.parentId": an invalid query ("zone" is not defined)
        $this->assertStringContainsString(
            'WHERE alias.zone IN (:zoneIN0) AND alias.zoneId = :zoneIdEQ1',
            $this->repository->findAllQueryBuilder($filters)->getDQL()
        );

        $queryBuilder = $this->repository->createQueryBuilder('alias')->innerJoin('alias.address', 'zone');
        $applyFilters = new \ReflectionMethod($this->repository, 'applyFilters');

        $this->assertStringContainsString(
            'WHERE zone.parentId IN (:zoneIN0) AND zone.parentId = :zoneIdEQ1',
            $applyFilters->invoke($this->repository, $queryBuilder, $filters)->getDQL()
        );
    }

    /**
     * An unknown operator used to be ignored: the filter was dropped and every row was returned (e.g. a filter by owner or tenant)
     */
    #[DataProvider('dataApplyFiltersRejectsAnInvalidFilter')]
    public function testApplyFiltersRejectsAnInvalidFilter(array $filter, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->repository->findAllQueryBuilder([$filter]);
    }

    public static function dataApplyFiltersRejectsAnInvalidFilter(): iterable
    {
        yield 'unknown operator' => [['operator' => 'NEQ', 'field' => 'name', 'value' => 'a'], 'Invalid filter operator "NEQ".'];
        yield 'no operator' => [['field' => 'name', 'value' => 'a'], 'Invalid filter operator "null".'];
        yield 'not a string' => [['operator' => ['EQ'], 'field' => 'name', 'value' => 'a'], 'Invalid filter operator "array".'];
        yield 'range without pairs' => [['operator' => 'RANGE', 'field' => 'quantity', 'value' => [1, 2]], 'a list of [from, to] pairs is expected'];
        yield 'empty range' => [['operator' => 'RANGE', 'field' => 'quantity', 'value' => []], 'a list of [from, to] pairs is expected'];
    }

    public function testTruncate(): void
    {
        // It used to be a fatal error: the "_em" property, the "App:Entity" aliases and executeUpdate() were removed in ORM 3 / DBAL 4
        $this->assertTrue($this->repository->truncate());
        $this->assertSame(0, (int)$this->repository->getResultsNumber());
    }

    /**
     * @param list<BaseRepositoryItem> $items
     *
     * @return list<string>
     */
    private function names(array $items): array
    {
        return array_map(static fn(BaseRepositoryItem $item): string => $item->name, $items);
    }
}

/**
 * @extends EntityRepository<BaseRepositoryItem>
 */
class BaseRepositoryItemRepository extends EntityRepository
{
    use BaseRepository;
}

#[ORM\Entity(repositoryClass: BaseRepositoryItemRepository::class)]
#[ORM\Table(name: 'base_repository_item')]
class BaseRepositoryItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;

    #[ORM\Embedded(class: BaseRepositoryAddress::class)]
    public BaseRepositoryAddress $address;

    public function __construct(
        #[ORM\Column(length: 20)]
        #[Groups(['read'])]
        public string $name,
        #[ORM\Column(type: Types::INTEGER)]
        public int    $quantity,
    )
    {
        $this->address = new BaseRepositoryAddress("city-{$name}");
    }
}

#[ORM\Embeddable]
class BaseRepositoryAddress
{
    public function __construct(
        #[ORM\Column(length: 20)]
        public string $city,
    )
    {
    }
}
