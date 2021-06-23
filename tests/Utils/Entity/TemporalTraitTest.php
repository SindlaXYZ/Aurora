<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\Entity;

// Core
use DateTime;
use TypeError;

// PHPUnit
use PHPUnit\Framework\TestCase;

// Symfony
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\HttpClient;

// Aurora
use Sindla\Bundle\AuroraBundle\Entity\Super\TemporalCreatedTrait;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/Entity/TemporalTraitTest.php --no-coverage
 */
class TemporalTraitTest extends KernelTestCase
{
    private $kernelTest;
    private $containerTest;

    protected function setUp(): void
    {
        $this->kernelTest    = self::bootKernel();
        $this->containerTest = $this->kernelTest->getContainer();
    }

    public function testFake(): void
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
    }

    public function testTemporalCreatedTrait()
    {
        /** @var TemporalCreatedTrait $mock */
        $mock = $this->getMockForTrait('Sindla\Bundle\AuroraBundle\Entity\Super\TemporalCreatedTrait');

        $someDateTime = new \DateTimeImmutable('2021-01-12 01:02:03');
        $dateFormat   = 'Y-m-d H:i:s';
        $mock->setCreatedAt($someDateTime);
        $this->assertTrue($someDateTime->format($dateFormat) == $mock->getCreatedAt()->format($dateFormat));

//        $mock->expects($this->any())
//            ->method('setCreatedAt')
//            ->will($this->returnValue(TRUE));
    }

    public function testTemporalCreatedTraitException()
    {
        /** @var TemporalCreatedTrait $mock */
        $mock = $this->getMockForTrait('Sindla\Bundle\AuroraBundle\Entity\Super\TemporalCreatedTrait');

        $this->expectException(TypeError::class);

        $someDateTime = new DateTime('2021-01-12 01:02:03');
        $mock->setCreatedAt(new DateTime());
        $mock->getCreatedAt();
    }
}
