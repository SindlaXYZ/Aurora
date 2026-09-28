<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Entity\SuperAttribute\Timestampable;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableAvailableInterval;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableCroned;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampablePasswordExpire;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampablePasswordUpdated;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableSuspended;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableSuspendedInterval;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableSynchronized;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableTranslated;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableUpdated;

/**
 * clear; php vendor/phpunit/phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Entity/SuperAttribute/Timestampable/TimestampableImmutableSettersTest.php --no-coverage
 */
class TimestampableImmutableSettersTest extends TestCase
{
    #[DataProvider('dataSetters')]
    public function testASetterStoresAnImmutableDate(object $entity, string $setter, string $getter): void
    {
        $entity->$setter(new \DateTime('2024-01-02 03:04:05'));
        $date = $entity->$getter();

        // The columns are DATETIME_IMMUTABLE: a DateTime (accepted by the DateTimeInterface setter) was a conversion error on flush
        $this->assertInstanceOf(\DateTimeImmutable::class, $date);
        $this->assertSame(
            '2024-01-02 03:04:05',
            Type::getType(Types::DATETIME_IMMUTABLE)->convertToDatabaseValue($date, new PostgreSQLPlatform())
        );

        // The same instance: Doctrine compares the objects by identity, a copy would be an UPDATE of an unchanged date
        $immutable = new \DateTimeImmutable('2025-06-07 08:09:10');
        $entity->$setter($immutable);
        $this->assertSame($immutable, $entity->$getter());

        $entity->$setter(null);
        $this->assertNull($entity->$getter());
    }

    public static function dataSetters(): iterable
    {
        yield 'available from' => [new class { use TimestampableAvailableInterval; }, 'setAvailableFrom', 'getAvailableFrom'];
        yield 'available to' => [new class { use TimestampableAvailableInterval; }, 'setAvailableTo', 'getAvailableTo'];
        yield 'croned' => [new class { use TimestampableCroned; }, 'setCronedAt', 'getCronedAt'];
        yield 'password expire' => [new class { use TimestampablePasswordExpire; }, 'setPasswordExpireAt', 'getPasswordExpireAt'];
        yield 'password updated' => [new class { use TimestampablePasswordUpdated; }, 'setPasswordUpdatedAt', 'getPasswordUpdatedAt'];
        yield 'suspended' => [new class { use TimestampableSuspended; }, 'setSuspendedAt', 'getSuspendedAt'];
        yield 'suspended from' => [new class { use TimestampableSuspendedInterval; }, 'setSuspendedFrom', 'getSuspendedFrom'];
        yield 'suspended to' => [new class { use TimestampableSuspendedInterval; }, 'setSuspendedTo', 'getSuspendedTo'];
        yield 'synchronized' => [new class { use TimestampableSynchronized; }, 'setSynchronizedAt', 'getSynchronizedAt'];
        yield 'translated' => [new class { use TimestampableTranslated; }, 'setTranslatedAt', 'getTranslatedAt'];
        yield 'updated' => [new class { use TimestampableUpdated; }, 'setUpdatedAt', 'getUpdatedAt'];
    }
}
