<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Entity\SuperAnnotation;

use DateTime;
use TypeError;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\HttpClient;
use Sindla\Bundle\AuroraBundle\Entity\SuperAnnotation\TemporalCreatedTrait;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Entity/SuperAnnotation/TemporalTraitTest.php --no-coverage
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
        /** @var TemporalCreatedTrait $TemporalCreatedTrait */
        $TemporalCreatedTrait = $this->getMockForTrait('Sindla\Bundle\AuroraBundle\Entity\SuperAnnotation\TemporalCreatedTrait');

        $someDateTime = new \DateTimeImmutable('2021-01-12 01:02:03');
        $dateFormat   = 'Y-m-d H:i:s';
        $TemporalCreatedTrait->setCreatedAt($someDateTime);
        $this->assertTrue($someDateTime->format($dateFormat) == $TemporalCreatedTrait->getCreatedAt()->format($dateFormat));
    }

    public function testTemporalCreatedTraitException()
    {
        /** @var TemporalCreatedTrait $TemporalCreatedTrait */
        $TemporalCreatedTrait = $this->getMockForTrait('Sindla\Bundle\AuroraBundle\Entity\SuperAnnotation\TemporalCreatedTrait');

        $this->expectException(TypeError::class);
        $TemporalCreatedTrait->setCreatedAt(new DateTime());
        $TemporalCreatedTrait->getCreatedAt();
    }
}
