<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Entity\SuperAttribute\Identifiable;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Identifiable\IdentifiableBigintNonNullable;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Identifiable\IdentifiableBigintNonNullableWithTableAlias;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Identifiable\IdentifiableBigintNotNullableNonAutoincrement;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Identifiable\IdentifiableIntNonNullable;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Identifiable\IdentifiableIntNonNullableNonAutoincrement;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Identifiable\IdentifiableIntNonNullableStrategyCustom;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Identifiable\IdentifiableIntNonNullableStrategyNone;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Identifiable\IdentifiableIntNonNullableWithTableAlias;

class IdentifiableTraitsTest extends TestCase
{
    /**
     * getId() on a new entity (e.g. "{% if entity.id %}" in a form template) used to be an Error: the typed property "must not be
     * accessed before initialization"
     */
    #[DataProvider('dataEntities')]
    public function testANewEntityHasNoId(object $entity): void
    {
        $this->assertNull($entity->getId());
    }

    #[DataProvider('dataEntities')]
    public function testSetId(object $entity): void
    {
        // The custom strategy identifier is a string (an UUID as hex), the others are integers
        $id = 'string' === (string)new \ReflectionMethod($entity, 'setId')->getParameters()[0]->getType() ? '0x0190a0b1c2d37e4f8a9b0c1d2e3f4a5b' : 42;

        $this->assertSame($entity, $entity->setId($id));
        $this->assertSame($id, $entity->getId());
    }

    public static function dataEntities(): iterable
    {
        yield 'int' => [new class {
            use IdentifiableIntNonNullable;
        }];
        yield 'int, table alias' => [new class {
            use IdentifiableIntNonNullableWithTableAlias;

            public const string TABLE_ALIAS = 'entity';
        }];
        yield 'int, not auto-increment' => [new class {
            use IdentifiableIntNonNullableNonAutoincrement;
        }];
        yield 'int, strategy none' => [new class {
            use IdentifiableIntNonNullableStrategyNone;
        }];
        yield 'string, strategy custom' => [new class {
            use IdentifiableIntNonNullableStrategyCustom;
        }];
        yield 'bigint' => [new class {
            use IdentifiableBigintNonNullable;
        }];
        yield 'bigint, table alias' => [new class {
            use IdentifiableBigintNonNullableWithTableAlias;

            public const string TABLE_ALIAS = 'entity';
        }];
        yield 'bigint, not auto-increment' => [new class {
            use IdentifiableBigintNotNullableNonAutoincrement;
        }];
    }

    /**
     * Doctrine unsets the identifier of a removed entity: getId() used to be an Error
     */
    #[RequiresPhpExtension('pdo_sqlite')]
    public function testARemovedEntityHasNoId(): void
    {
        $config = method_exists(ORMSetup::class, 'createAttributeMetadataConfig')
            ? ORMSetup::createAttributeMetadataConfig([], true)
            : ORMSetup::createAttributeMetadataConfiguration([], true);

        if (PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        new SchemaTool($em)->createSchema([$em->getClassMetadata(IdentifiableTraitsEntity::class)]);

        $entity = new IdentifiableTraitsEntity();
        $em->persist($entity);
        $em->flush();

        $this->assertIsInt($entity->getId());

        $em->remove($entity);
        $em->flush();

        $this->assertNull($entity->getId());
    }

    /**
     * Without a generator the identifier is the one assigned with setId()
     */
    #[RequiresPhpExtension('pdo_sqlite')]
    public function testANonAutoincrementEntityIsPersistedWithTheAssignedId(): void
    {
        $config = method_exists(ORMSetup::class, 'createAttributeMetadataConfig')
            ? ORMSetup::createAttributeMetadataConfig([], true)
            : ORMSetup::createAttributeMetadataConfiguration([], true);

        if (PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        new SchemaTool($em)->createSchema([$em->getClassMetadata(IdentifiableTraitsAssignedIdEntity::class)]);

        $this->assertSame(ClassMetadata::GENERATOR_TYPE_NONE, $em->getClassMetadata(IdentifiableTraitsAssignedIdEntity::class)->generatorType);

        $em->persist(new IdentifiableTraitsAssignedIdEntity()->setId(5000000000));
        $em->flush();
        $em->clear();

        $entity = $em->find(IdentifiableTraitsAssignedIdEntity::class, 5000000000);

        $this->assertInstanceOf(IdentifiableTraitsAssignedIdEntity::class, $entity);
        $this->assertSame(5000000000, $entity->getId());
    }

    /**
     * The sequence of the "WithTableAlias" traits is named after the TABLE_ALIAS constant of the entity
     */
    #[DataProvider('dataEntitiesWithTableAlias')]
    public function testTheSequenceIsNamedAfterTheTableAlias(object $entity): void
    {
        $sequenceGenerator = new \ReflectionProperty($entity, 'id')->getAttributes(ORM\SequenceGenerator::class)[0]->newInstance();

        $this->assertSame('invoice_id_seq', $sequenceGenerator->sequenceName);
        $this->assertSame(1, $sequenceGenerator->allocationSize);
    }

    public static function dataEntitiesWithTableAlias(): iterable
    {
        yield 'int' => [new class {
            use IdentifiableIntNonNullableWithTableAlias;

            public const string TABLE_ALIAS = 'invoice';
        }];
        yield 'bigint' => [new class {
            use IdentifiableBigintNonNullableWithTableAlias;

            public const string TABLE_ALIAS = 'invoice';
        }];
    }
}

#[ORM\Entity]
#[ORM\Table(name: 'identifiable_traits_entity')]
class IdentifiableTraitsEntity
{
    use IdentifiableIntNonNullableStrategyNone;
}

#[ORM\Entity]
#[ORM\Table(name: 'identifiable_traits_assigned_id_entity')]
class IdentifiableTraitsAssignedIdEntity
{
    use IdentifiableBigintNotNullableNonAutoincrement;
}
