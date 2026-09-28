<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\EventSubscriber;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Entity\SuperClass\AbstractTimestampableDeletedNotNullable;
use Sindla\Bundle\AuroraBundle\EventSubscriber\SoftDeleteIndexSubscriber;

class SoftDeleteIndexSubscriberTest extends TestCase
{
    private const string TABLE = 'soft_delete_index_article';

    public function testAddsTheSoftDeleteIndexNamedAfterTheTable(): void
    {
        $metadata = self::createMetadata(SoftDeleteIndexSubscriberArticle::class);

        $this->loadClassMetadata($metadata);

        $this->assertSame(['idx_soft_delete_index_article_soft_delete' => ['columns' => ['deleted_at']]], $metadata->table['indexes']);
    }

    #[DataProvider('dataOtherIndexes')]
    public function testKeepsTheOtherIndexes(array $columns): void
    {
        $metadata = self::createMetadata(SoftDeleteIndexSubscriberArticle::class);
        $metadata->table['indexes']['custom_idx'] = ['columns' => $columns];

        $this->loadClassMetadata($metadata);

        $this->assertSame(
            ['custom_idx' => ['columns' => $columns], 'idx_soft_delete_index_article_soft_delete' => ['columns' => ['deleted_at']]],
            $metadata->table['indexes']
        );
    }

    public static function dataOtherIndexes(): iterable
    {
        yield 'another column' => [['title']];
        yield 'composite index that contains deleted_at' => [['title', 'deleted_at']];
    }

    public function testAnIndexDefinedOnDeletedAtIsNotDuplicated(): void
    {
        // Defined by the application, e.g. #[ORM\Index(name: 'custom_deleted_idx', columns: ['deleted_at'])]
        $metadata = self::createMetadata(SoftDeleteIndexSubscriberArticle::class);
        $metadata->table['indexes']['custom_deleted_idx'] = ['columns' => ['deleted_at']];

        $this->loadClassMetadata($metadata);

        $this->assertSame(['custom_deleted_idx' => ['columns' => ['deleted_at']]], $metadata->table['indexes']);
    }

    #[DataProvider('dataSkippedMetadata')]
    public function testSkippedMetadataGetsNoIndex(ClassMetadata $metadata): void
    {
        $this->loadClassMetadata($metadata);

        $this->assertArrayNotHasKey('indexes', $metadata->table);
    }

    public static function dataSkippedMetadata(): iterable
    {
        $mappedSuperclass                     = self::createMetadata(SoftDeleteIndexSubscriberArticle::class);
        $mappedSuperclass->isMappedSuperclass = true;
        yield 'mapped superclass' => [$mappedSuperclass];

        $embeddable                  = self::createMetadata(SoftDeleteIndexSubscriberArticle::class);
        $embeddable->isEmbeddedClass = true;
        yield 'embeddable' => [$embeddable];

        yield 'entity that does not extend the soft delete superclass' => [self::createMetadata(\stdClass::class)];
        yield 'without the deletedAt field' => [self::createMetadata(SoftDeleteIndexSubscriberArticle::class, false)];
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testTheIndexIsCreatedWithTheTable(): void
    {
        $config = method_exists(ORMSetup::class, 'createAttributeMetadataConfig')
            ? ORMSetup::createAttributeMetadataConfig([], true)
            : ORMSetup::createAttributeMetadataConfiguration([], true);

        if (PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        $em->getEventManager()->addEventListener(Events::loadClassMetadata, new SoftDeleteIndexSubscriber());

        $sql = new SchemaTool($em)->getCreateSchemaSql([$em->getClassMetadata(SoftDeleteIndexSubscriberArticle::class)]);

        $this->assertContains('CREATE INDEX idx_soft_delete_index_article_soft_delete ON soft_delete_index_article (deleted_at)', $sql);
    }

    private static function createMetadata(string $className, bool $withDeletedAt = true): ClassMetadata
    {
        $metadata = new ClassMetadata($className);
        $metadata->setPrimaryTable(['name' => self::TABLE]);

        if ($withDeletedAt) {
            $metadata->mapField(['fieldName' => 'deletedAt', 'columnName' => 'deleted_at', 'type' => Types::DATETIME_IMMUTABLE, 'nullable' => true]);
        }

        return $metadata;
    }

    private function loadClassMetadata(ClassMetadata $metadata): void
    {
        new SoftDeleteIndexSubscriber()->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $this->createStub(EntityManagerInterface::class)));
    }
}

#[ORM\Entity]
#[ORM\Table(name: 'soft_delete_index_article')]
class SoftDeleteIndexSubscriberArticle extends AbstractTimestampableDeletedNotNullable
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;
}
