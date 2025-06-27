<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Entity\SuperAttribute\Timestampable;

use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableCreated;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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

        $timestampableCreated = $this->createMock(TimestampableCreatedMock::class);
        $timestampableCreated->method('getCreatedAt')->willReturn($datetime);

        $someDateTime = clone $datetime;
        $dateFormat   = 'Y-m-d H:i:s';
        $this->assertEquals(
            $someDateTime->format($dateFormat),
            $timestampableCreated->getCreatedAt()->format($dateFormat)
        );
    }

    public function testTimestampableCreatedException(): void
    {
        /** @var TimestampableCreated $timestampableCreated */
        $timestampableCreated = $this->createMock(TimestampableCreatedMock::class);

        $this->expectException(\TypeError::class);
        $timestampableCreated->setCreatedAt(new \DateTime())->getCreatedAt();
    }
}

class TimestampableCreatedMock
{
    use TimestampableCreated;
}
