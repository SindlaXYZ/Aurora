<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Entity\SuperAttribute\Timestampable;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableDeletedMutable;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableDeletedNotNullable;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableDeletedNullable;
use Sindla\Bundle\AuroraBundle\Entity\SuperClass\AbstractTimestampableDeletedNotNullable;
use Sindla\Bundle\AuroraBundle\Entity\SuperClass\AbstractTimestampableDeletedNullable;

/**
 * The soft delete traits and their mapped superclasses: an entity is deleted once its deleted_at has passed. The "not nullable" ones replace a
 * null deleted_at with a default date in the future (AuroraConstants::TIMESTAMPABLE_DELETED_DEFAULT_DELETED_AT) so that it can be part of a
 * unique key. The lifespans are tested by TimestampableLifespanTest.
 */
class TimestampableDeletedTest extends TestCase
{
    private const int TEN_DAYS = 864000;

    #[DataProvider('dataNotNullable')]
    public function testPrePersistSetsTheDefaultDeletedAt(object $entity): void
    {
        $entity->prePersistDeletedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $entity->getDeletedAt());
        $this->assertSame(AuroraConstants::TIMESTAMPABLE_DELETED_DEFAULT_DELETED_AT, $entity->getDeletedAt()->format('Y-m-d H:i:s.u'));

        // The default date is neither a deletion nor a scheduled one
        $this->assertFalse($entity->isDeleted());
        $this->assertFalse($entity->isDeletedInFuture());
    }

    #[DataProvider('dataNotNullable')]
    public function testPrePersistKeepsTheDeletedAt(object $entity): void
    {
        $deletedAt = new \DateTimeImmutable('2024-01-02 03:04:05', new \DateTimeZone('UTC'));

        $this->assertSame($entity, $entity->setDeletedAt($deletedAt));
        $entity->prePersistDeletedAt();

        $this->assertSame($deletedAt, $entity->getDeletedAt());
    }

    public static function dataNotNullable(): iterable
    {
        yield 'trait' => [new class {
            use TimestampableDeletedNotNullable;
        }];
        yield 'mapped superclass' => [new class extends AbstractTimestampableDeletedNotNullable {
        }];
    }

    #[DataProvider('dataDeleted')]
    public function testIsDeleted(object $entity): void
    {
        $this->assertNull($entity->getDeletedAt());
        $this->assertFalse($entity->isDeleted());
        $this->assertFalse($entity->isDeletedInFuture());

        $entity->setDeletedAt(new \DateTimeImmutable(sprintf('@%d', time() - self::TEN_DAYS)));
        $this->assertTrue($entity->isDeleted());
        $this->assertFalse($entity->isDeletedInFuture());

        // A deletion scheduled in the future
        $deletedAt = new \DateTimeImmutable(sprintf('@%d', time() + self::TEN_DAYS));
        $entity->setDeletedAt($deletedAt);
        $this->assertSame($deletedAt, $entity->getDeletedAt());
        $this->assertFalse($entity->isDeleted());
        $this->assertTrue($entity->isDeletedInFuture());

        // Restored
        $entity->setDeletedAt(null);
        $this->assertFalse($entity->isDeleted());
        $this->assertFalse($entity->isDeletedInFuture());
    }

    public static function dataDeleted(): iterable
    {
        yield 'not nullable trait' => [new class {
            use TimestampableDeletedNotNullable;
        }];
        yield 'not nullable mapped superclass' => [new class extends AbstractTimestampableDeletedNotNullable {
        }];
        yield 'nullable trait' => [new class {
            use TimestampableDeletedNullable;
        }];
        yield 'nullable mapped superclass' => [new class extends AbstractTimestampableDeletedNullable {
        }];
    }

    public function testIsDeletedMutable(): void
    {
        $entity = new class {
            use TimestampableDeletedMutable;
        };

        $this->assertNull($entity->getDeletedAt());
        $this->assertFalse($entity->isDeleted());

        $deletedAt = new \DateTime(sprintf('@%d', time() - self::TEN_DAYS));
        $this->assertSame($entity, $entity->setDeletedAt($deletedAt));
        $this->assertSame($deletedAt, $entity->getDeletedAt());
        $this->assertTrue($entity->isDeleted());

        $entity->setDeletedAt(null);
        $this->assertFalse($entity->isDeleted());
    }
}
