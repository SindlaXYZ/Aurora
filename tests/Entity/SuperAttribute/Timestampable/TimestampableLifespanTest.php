<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Entity\SuperAttribute\Timestampable;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableCreated;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableCroned;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableDeletedMutable;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableDeletedNotNullable;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableDeletedNullable;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampablePasswordExpire;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampablePasswordUpdated;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableResponded;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableSuspended;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableSynchronized;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableTranslated;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableUpdated;
use Sindla\Bundle\AuroraBundle\Entity\SuperClass\AbstractTimestampableDeletedNotNullable;
use Sindla\Bundle\AuroraBundle\Entity\SuperClass\AbstractTimestampableDeletedNullable;

/**
 * The get*LifespanAs{Seconds,Minutes,Hours,Days}() of the single date Timestampable traits and of the deleted_at mapped superclasses: the time
 * elapsed since the date, negative for a date in the future. The interval traits are tested by TimestampableIntervalTest.
 */
class TimestampableLifespanTest extends TestCase
{
    private const int TEN_DAYS = 864000;

    #[DataProvider('dataDates')]
    public function testWithoutADateTheLifespanIsZero(object $entity, string $date, string $dateClass): void
    {
        $this->assertNull($entity->{sprintf('get%s', $date)}());
        $this->assertSame([0, 0, 0, 0], $this->lifespan($entity, $date));
    }

    #[DataProvider('dataDates')]
    public function testTheLifespanOfAPastDate(object $entity, string $date, string $dateClass): void
    {
        $now = time();
        $entity->{sprintf('set%s', $date)}(new $dateClass(sprintf('@%d', $now - self::TEN_DAYS)));

        [$seconds, $minutes, $hours, $days] = $this->lifespan($entity, $date);

        // A second may have elapsed since the date was set
        $this->assertGreaterThanOrEqual(self::TEN_DAYS, $seconds);
        $this->assertLessThanOrEqual(self::TEN_DAYS + time() - $now, $seconds);
        $this->assertSame([14400, 240, 10], [$minutes, $hours, $days]);
    }

    #[DataProvider('dataDates')]
    public function testTheLifespanOfAFutureDateIsNegative(object $entity, string $date, string $dateClass): void
    {
        $now = time();
        $entity->{sprintf('set%s', $date)}(new $dateClass(sprintf('@%d', $now + self::TEN_DAYS)));

        [$seconds, $minutes, $hours, $days] = $this->lifespan($entity, $date);

        $this->assertGreaterThanOrEqual(-self::TEN_DAYS, $seconds);
        $this->assertLessThanOrEqual(-self::TEN_DAYS + time() - $now, $seconds);
        $this->assertSame([-14400, -240, -10], [$minutes, $hours, $days]);
    }

    public static function dataDates(): iterable
    {
        yield 'created' => [new class {
            use TimestampableCreated;
        }, 'CreatedAt', \DateTimeImmutable::class];
        yield 'updated' => [new class {
            use TimestampableUpdated;
        }, 'UpdatedAt', \DateTimeImmutable::class];
        yield 'croned' => [new class {
            use TimestampableCroned;
        }, 'CronedAt', \DateTimeImmutable::class];
        yield 'deleted, mutable' => [new class {
            use TimestampableDeletedMutable;
        }, 'DeletedAt', \DateTime::class];
        yield 'deleted, not nullable' => [new class {
            use TimestampableDeletedNotNullable;
        }, 'DeletedAt', \DateTimeImmutable::class];
        yield 'deleted, nullable' => [new class {
            use TimestampableDeletedNullable;
        }, 'DeletedAt', \DateTimeImmutable::class];
        yield 'deleted, not nullable superclass' => [new class extends AbstractTimestampableDeletedNotNullable {
        }, 'DeletedAt', \DateTimeImmutable::class];
        yield 'deleted, nullable superclass' => [new class extends AbstractTimestampableDeletedNullable {
        }, 'DeletedAt', \DateTimeImmutable::class];
        yield 'password expire' => [new class {
            use TimestampablePasswordExpire;
        }, 'PasswordExpireAt', \DateTimeImmutable::class];
        yield 'password updated' => [new class {
            use TimestampablePasswordUpdated;
        }, 'PasswordUpdatedAt', \DateTimeImmutable::class];
        yield 'responded' => [new class {
            use TimestampableResponded;
        }, 'RespondedAt', \DateTimeImmutable::class];
        yield 'suspended' => [new class {
            use TimestampableSuspended;
        }, 'SuspendedAt', \DateTimeImmutable::class];
        yield 'synchronized' => [new class {
            use TimestampableSynchronized;
        }, 'SynchronizedAt', \DateTimeImmutable::class];
        yield 'translated' => [new class {
            use TimestampableTranslated;
        }, 'TranslatedAt', \DateTimeImmutable::class];
    }

    /**
     * 1.5 of a unit is rounded up to 2, not truncated to 1
     *
     * @param array{int, int, int} $expected The lifespan as minutes, hours and days
     */
    #[DataProvider('dataRounding')]
    public function testTheLifespanIsRoundedHalfUp(int $secondsAgo, array $expected): void
    {
        $entity = new class {
            use TimestampableCreated;
        };
        $entity->setCreatedAt(new \DateTimeImmutable(sprintf('@%d', time() - $secondsAgo)));

        $this->assertSame($expected, array_slice($this->lifespan($entity, 'CreatedAt'), 1));
    }

    public static function dataRounding(): iterable
    {
        yield '1.5 minutes' => [90, [2, 0, 0]];
        yield '1.5 hours' => [5400, [90, 2, 0]];
        yield '1.5 days' => [129600, [2160, 36, 2]];
    }

    #[DataProvider('dataIsDated')]
    public function testIsTrueOnlyWithADate(object $entity, string $date, string $isDated): void
    {
        $this->assertFalse($entity->$isDated());

        $entity->{sprintf('set%s', $date)}(new \DateTimeImmutable('2024-01-02 03:04:05', new \DateTimeZone('UTC')));
        $this->assertTrue($entity->$isDated());

        $entity->{sprintf('set%s', $date)}(null);
        $this->assertFalse($entity->$isDated());
    }

    public static function dataIsDated(): iterable
    {
        yield 'created' => [new class {
            use TimestampableCreated;
        }, 'CreatedAt', 'isPersisted'];
        yield 'updated' => [new class {
            use TimestampableUpdated;
        }, 'UpdatedAt', 'isUpdated'];
        yield 'croned' => [new class {
            use TimestampableCroned;
        }, 'CronedAt', 'isCroned'];
        yield 'responded' => [new class {
            use TimestampableResponded;
        }, 'RespondedAt', 'isResponded'];
        yield 'suspended' => [new class {
            use TimestampableSuspended;
        }, 'SuspendedAt', 'isSuspended'];
        yield 'synchronized' => [new class {
            use TimestampableSynchronized;
        }, 'SynchronizedAt', 'isSynchronized'];
        yield 'translated' => [new class {
            use TimestampableTranslated;
        }, 'TranslatedAt', 'isTranslated'];
    }

    public function testASuspensionInTheFuture(): void
    {
        $entity = new class {
            use TimestampableSuspended;
        };

        $this->assertFalse($entity->getSuspendedInTheFuture());

        $entity->setSuspendedAt(new \DateTimeImmutable(sprintf('@%d', time() + self::TEN_DAYS)));
        $this->assertTrue($entity->getSuspendedInTheFuture());

        $entity->setSuspendedAt(new \DateTimeImmutable(sprintf('@%d', time() - self::TEN_DAYS)));
        $this->assertFalse($entity->getSuspendedInTheFuture());
    }

    /**
     * @return array{int, int, int, int} The lifespan as seconds, minutes, hours and days
     */
    private function lifespan(object $entity, string $date): array
    {
        return array_map(
            static fn(string $unit): int => $entity->{sprintf('get%sLifespanAs%s', $date, $unit)}(),
            ['Seconds', 'Minutes', 'Hours', 'Days']
        );
    }
}
