<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Entity\SuperAnnotation;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Sindla\Bundle\AuroraBundle\Entity\SuperAnnotation\IdentifiableUUID;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Entity/SuperAnnotation/IdentifiableUUIDTest.php --no-coverage
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
        $Mock->generateId();
        $this->assertIsString($Mock->getId()->toBinary());
    }
}

class Mock
{
    use IdentifiableUUID;
}
