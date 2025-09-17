<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraIP;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraIP\AuroraIP;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/AuroraIP/AuroraIPTest.php --no-coverage
 */
class AuroraIPTest extends TestCase
{

    public function testFake(): void
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
    }

    /**
     * @dataProvider dataIsGoogleBot
     */
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

    /**
     * @dataProvider dataIsPrivate
     */
    #[DataProvider('dataIsPrivate')]
    public function testIsPrivate(string $given, bool $expected): void
    {
        $this->assertEquals(
            $expected,
            (new AuroraIP())->isPrivate($given),
            'Given IP: ' . $given . ' != ' . ($expected ? 'true' : 'false')
        );
    }

    public static function dataIsPrivate(): array
    {
        return [
            ['127.0.0.1', true],
            ['999.999.999.999', false]
        ];
    }

    /**
     * @dataProvider dataIsIPInSubnet
     */
    #[DataProvider('dataIsIPInSubnet')]
    public function testIsIPInSubnet(string $ip, string $cidr, bool $expected): void
    {
        $this->assertEquals($expected, (new AuroraIP())->isIPInSubnet($ip, $cidr));
    }

    public static function dataIsIPInSubnet(): array
    {
        return [
            ['192.168.1.5', '192.168.1.0/24', true],
            ['192.168.2.5', '192.168.1.0/24', false],
            ['2001:db8::1', '2001:db8::/32', true],
            ['2001:db9::1', '2001:db8::/32', false],
            ['192.168.1.5', '192.168.1.0/33', false],
            ['192.168.1.5', '192.168.1.5', false],
            ['2001:db8::1', '2001:db8::', false],
            ['192.168.1.5', '192.168.1.0/24 ', true],
            ['192.168.1.5', ' 192.168.1.0/24', true],
            ['192.168.1.5', '192.168.1.0', false],
        ];
    }
}
