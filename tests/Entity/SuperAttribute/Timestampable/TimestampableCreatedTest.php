<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Entity\SuperAttribute\Timestampable;

use DateTime;
use TypeError;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\HttpClient;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableCreated;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Entity/SuperAttribute/Timestampable/TimestampableCreated.php --no-coverage
 */
class TimestampableCreatedTest extends KernelTestCase
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

    public function testTimestampableCreated(): void
    {
        /** @var TimestampableCreated $timestampableCreated */
        $timestampableCreated = $this->getMockForTrait('Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableCreated');

        $someDateTime = new \DateTimeImmutable('2021-01-12 01:02:03');
        $dateFormat   = 'Y-m-d H:i:s';
        $timestampableCreated->setCreatedAt($someDateTime);
        $this->assertTrue($someDateTime->format($dateFormat) == $timestampableCreated->getCreatedAt()->format($dateFormat));
    }

    public function testTimestampableCreatedException(): void
    {
        /** @var TimestampableCreated $timestampableCreated */
        $timestampableCreated = $this->getMockForTrait('Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableCreated');

        $this->expectException(TypeError::class);
        $timestampableCreated->setCreatedAt(new DateTime());
        $timestampableCreated->getCreatedAt();
    }
}
