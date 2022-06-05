<?php declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Entity\Super;

// PHPUnit
use PHPUnit\Framework\TestCase;

// Symfony
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

// Aurora
use Sindla\Bundle\AuroraBundle\Entity\Super\IdentifiableUUID;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Entity/Entity/IdentifiableUUIDTest.php --no-coverage
 */
class IdentifiableUUIDTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @group aurora
     */
    public function testIdentifiableUUID()
    {
        $Mock = new Mock();
        $this->assertInternalType('string', $Mock->getId()->toBinary());
    }
}

class Mock
{
    use IdentifiableUUID;
}
