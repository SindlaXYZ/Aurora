<?php declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\EventListener;

// PHPUnit
use PHPUnit\Framework\TestCase;

// Symfony
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

// Sindla
use Sindla\Bundle\AuroraBundle\EventListener\OutputSubscriber;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/EventListener/OutputSubscriberTest.php --no-coverage
 */
class OutputSubscriberTest extends KernelTestCase
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

    public function testXRobotsTag(): void
    {
        // Test domain prefix
        $this->assertFalse(boolval(preg_match(OutputSubscriber::PREG_DEV_PREFIX, 'stagingoind.com')));
        $this->assertTrue(boolval(preg_match(OutputSubscriber::PREG_DEV_PREFIX, 'stg.singla.com')));

        $this->assertTrue(boolval(preg_match(OutputSubscriber::PREG_DEV_PREFIX, 'dev.sindla.com')));
        $this->assertFalse(boolval(preg_match(OutputSubscriber::PREG_DEV_PREFIX, 'developer.com')));

        // Test domain suffix
        $this->assertFalse(boolval(preg_match(OutputSubscriber::PREG_DEV_SUFFIX, 'sindla.com')));
        $this->assertFalse(boolval(preg_match(OutputSubscriber::PREG_DEV_SUFFIX, 'ns1.sindla.local.com')));

        $this->assertTrue(boolval(preg_match(OutputSubscriber::PREG_DEV_SUFFIX, 'sindla.com.localhost')));
        $this->assertTrue(boolval(preg_match(OutputSubscriber::PREG_DEV_SUFFIX, 'sindla.com.local')));
    }
}