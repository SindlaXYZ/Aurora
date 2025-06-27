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
        $datetime = new \DateTimeImmutable('2021-01-12 01:02:03');

        /** @var TimestampableCreated $timestampableCreated */
        $timestampableCreated = $this->createMock(TimestampableCreatedMock::class)->setCreatedAt($datetime);

        $someDateTime = $datetime;
        $dateFormat   = 'Y-m-d H:i:s';
        $timestampableCreated->setCreatedAt($someDateTime);
        $this->assertTrue($someDateTime->format($dateFormat) == $timestampableCreated->getCreatedAt()->format($dateFormat));
    }

    public function testTimestampableCreatedException(): void
    {
        /** @var TimestampableCreated $timestampableCreated */
        $timestampableCreated = $this->createMock(TimestampableCreatedMock::class);

        $this->expectException(TypeError::class);
        $timestampableCreated->setCreatedAt(new DateTime())->getCreatedAt();
    }
}

class TimestampableCreatedMock
{
    use TimestampableCreated;
}
