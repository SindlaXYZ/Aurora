<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Entity\SuperAttribute\Identifiable;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Identifiable\IdentifiableIntNonNullableStrategyCustom;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Entity/SuperAttribute/Identifiable/IdentifiableIntNonNullableStrategyCustomTest.php --no-coverage
 */
class IdentifiableIntNonNullableStrategyCustomTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @group aurora
     */
    public function testIdentifiableUUID(): void
    {
        $Mock = new Mock()
            ->generateId();
        $this->assertIsString($Mock->getId());
    }
}

class Mock
{
    use IdentifiableIntNonNullableStrategyCustom;
}
