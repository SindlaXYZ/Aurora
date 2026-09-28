<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Entity\SuperAttribute\Identifiable;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
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
}

#[ORM\Entity]
#[ORM\Table(name: 'identifiable_traits_entity')]
class IdentifiableTraitsEntity
{
    use IdentifiableIntNonNullableStrategyNone;
}
