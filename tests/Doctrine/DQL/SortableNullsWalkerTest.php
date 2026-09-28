<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Doctrine\DQL;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Doctrine\DQL\SortableNullsWalker;

class SortableNullsWalkerTest extends TestCase
{
    public function testAppendsTheNullsOrderingOfTheHintedFields(): void
    {
        $em    = $this->createEntityManager(['driver' => 'pdo_pgsql', 'serverVersion' => '16']);
        $query = $this->createQuery($em, 'SELECT p.id FROM %s p ORDER BY p.firstName ASC, p.lastName DESC, p.id DESC', [
            'p.firstName' => SortableNullsWalker::NULLS_FIRST,
            'p.lastName'  => SortableNullsWalker::NULLS_LAST,
        ]);

        $this->assertSame(
            'SELECT s0_.id AS id_0 FROM sortable_nulls_walker_person s0_'
            . ' ORDER BY s0_.first_name ASC NULLS FIRST, s0_.last_name DESC NULLS LAST, s0_.id DESC',
            $query->getSQL()
        );
        $this->assertFalse($em->getConnection()->isConnected());
    }

    #[DataProvider('dataLeavesTheOrderByUnchanged')]
    public function testLeavesTheOrderByUnchanged(string $dql, mixed $hint, string $expectedOrderBy): void
    {
        $query = $this->createQuery($this->createEntityManager(['driver' => 'pdo_pgsql', 'serverVersion' => '16']), $dql, $hint);

        $this->assertStringEndsWith(sprintf(' ORDER BY %s', $expectedOrderBy), $query->getSQL());
    }

    public static function dataLeavesTheOrderByUnchanged(): iterable
    {
        $dql = 'SELECT p.id FROM %s p ORDER BY p.firstName ASC';

        yield 'no hint' => [$dql, null, 's0_.first_name ASC'];
        yield 'an empty hint' => [$dql, [], 's0_.first_name ASC'];
        yield 'a hint that is not a list' => [$dql, 'p.firstName NULLS FIRST', 's0_.first_name ASC'];
        yield 'a field that is not hinted' => [$dql, ['p.lastName' => SortableNullsWalker::NULLS_FIRST], 's0_.first_name ASC'];
        yield 'a result variable' => [
            'SELECT p.id, LENGTH(p.firstName) AS nameLength FROM %s p ORDER BY nameLength DESC',
            ['nameLength' => SortableNullsWalker::NULLS_LAST],
            'sclr_1 DESC',
        ];
    }

    /**
     * @param list<string|null> $expectedFirstNames
     */
    #[RequiresPhpExtension('pdo_sqlite')]
    #[DataProvider('dataSortsTheNulls')]
    public function testSortsTheNulls(string $direction, string $nulls, array $expectedFirstNames): void
    {
        $em = $this->createEntityManager(['driver' => 'pdo_sqlite', 'memory' => true]);
        new SchemaTool($em)->createSchema([$em->getClassMetadata(SortableNullsWalkerPerson::class)]);

        foreach (['b', null, 'a'] as $firstName) {
            $em->persist(new SortableNullsWalkerPerson($firstName));
        }

        $em->flush();

        // SQLite sorts the NULLs first in ascending order and last in descending order: both cases reverse its default
        $query = $this->createQuery($em, sprintf('SELECT p.firstName FROM %%s p ORDER BY p.firstName %s', $direction), ['p.firstName' => $nulls]);

        $this->assertSame($expectedFirstNames, array_column($query->getScalarResult(), 'firstName'));
    }

    public static function dataSortsTheNulls(): iterable
    {
        yield 'ascending, nulls last' => ['ASC', SortableNullsWalker::NULLS_LAST, ['a', 'b', null]];
        yield 'descending, nulls first' => ['DESC', SortableNullsWalker::NULLS_FIRST, [null, 'b', 'a']];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function createEntityManager(array $params): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfig([], true);
        $config->enableNativeLazyObjects(true);

        return new EntityManager(DriverManager::getConnection($params, $config), $config);
    }

    private function createQuery(EntityManager $em, string $dql, mixed $hint): Query
    {
        $query = $em->createQuery(sprintf($dql, SortableNullsWalkerPerson::class));
        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, SortableNullsWalker::class);

        if (null !== $hint) {
            $query->setHint('sortableNulls.fields', $hint);
        }

        return $query;
    }
}

#[ORM\Entity]
#[ORM\Table(name: 'sortable_nulls_walker_person')]
class SortableNullsWalkerPerson
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;

    #[ORM\Column(name: 'last_name', length: 20, nullable: true)]
    public ?string $lastName = null;

    public function __construct(
        #[ORM\Column(name: 'first_name', length: 20, nullable: true)]
        public ?string $firstName,
    ) {
    }
}
