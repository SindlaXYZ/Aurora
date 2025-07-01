<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraClient;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraIP\AuroraIP;
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

    #[DataProvider('dataIsGoogleBot')]
    public function testIsGoogleBot(string $given, bool $expected): void
    {
        $this->assertEquals(
            $expected,
            new AuroraIP()->isGoogle($given),
            'Given IP: ' . $given . ' != ' . ($expected ? 'true' : 'false')
        );
    }

    public static function dataIsGoogleBot(): array
    {
        return [
            ['66.249.69.69', true],
            ['66.102.9.32', true],
            ['66.102.9.41', true],
            ['66.249.77.70', true],
            ['66.249.83.82', true],
            ['194.59.207.87', false],
            ['89.58.53.13', false],
            ['136.243.89.232', false]
        ];
    }
}
