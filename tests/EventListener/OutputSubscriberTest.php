<?php

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

    public function testFake()
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
    }

    public function testXRobotsTag()
    {
        $this->assertFalse(boolval(preg_match(OutputSubscriber::PREG_DEV_PREFIX, 'stagingoind.com')));
    }
}