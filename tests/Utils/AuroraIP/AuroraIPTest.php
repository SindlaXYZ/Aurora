<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraClient;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/AuroraIP/AuroraIPTest.php --no-coverage
 */
class AuroraIPTest extends KernelTestCase
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
}
