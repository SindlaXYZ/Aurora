<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Entity\SuperAttribute\Identifiable;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Identifiable\LegacyIntNullable;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Identifiable\LegacyStringNullable;

/**
 * LegacyIntNullable and LegacyStringNullable: the identifier of the record in a legacy system, next to the entity identifier
 */
class LegacyNullableTest extends TestCase
{
    public function testLegacyId(): void
    {
        $entity = new class {
            use LegacyIntNullable;
        };

        $this->assertNull($entity->getLegacyId());

        $this->assertSame($entity, $entity->setLegacyId(1234));
        $this->assertSame(1234, $entity->getLegacyId());

        // Without an argument, the legacy identifier is removed
        $entity->setLegacyId();
        $this->assertNull($entity->getLegacyId());
    }

    public function testLegacyIdentifier(): void
    {
        $entity = new class {
            use LegacyStringNullable;
        };

        $this->assertNull($entity->getLegacyIdentifier());

        $this->assertSame($entity, $entity->setLegacyIdentifier('LEGACY-0042'));
        $this->assertSame('LEGACY-0042', $entity->getLegacyIdentifier());

        // Without an argument, the legacy identifier is removed
        $entity->setLegacyIdentifier();
        $this->assertNull($entity->getLegacyIdentifier());
    }
}
