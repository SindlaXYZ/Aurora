<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraIP;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraIP\AuroraIP;
use Symfony\Component\HttpFoundation\Request;

/**
 * clear; php vendor/phpunit/phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/AuroraIP/AuroraIPTest.php --no-coverage
 */
class AuroraIPTest extends TestCase
{
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

    #[DataProvider('dataIsPrivate')]
    public function testIsPrivate(string $given, bool $expected): void
    {
        $this->assertEquals(
            $expected,
            new AuroraIP()->isPrivate($given),
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

    #[DataProvider('dataIsIPInSubnet')]
    public function testIsIPInSubnet(string $ip, string $cidr, bool $expected): void
    {
        $this->assertEquals($expected, new AuroraIP()->isIPInSubnet($ip, $cidr));
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

    public function testIpIgnoresForwardingHeadersFromAnUntrustedClient(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR'           => '198.51.100.5',
            'HTTP_X_FORWARDED_FOR'  => '203.0.113.10',
            'HTTP_CF_CONNECTING_IP' => '203.0.113.20',
            'HTTP_CLIENT_IP'        => '203.0.113.30',
        ]);

        $originalServer                   = $_SERVER;
        $_SERVER['HTTP_X_FORWARDED_FOR']  = '203.0.113.10';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.20';
        $_SERVER['HTTP_CLIENT_IP']        = '203.0.113.30';

        try {
            // These headers are set by the client: honouring them let anyone choose the IP that is logged and reported to the BlackHole API
            $this->assertSame('198.51.100.5', new AuroraIP()->ip($request));
        } finally {
            $_SERVER = $originalServer;
        }
    }

    public function testIpHonoursTheForwardedHeaderOfATrustedProxy(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR'          => '10.0.0.2',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9, 203.0.113.10',
        ]);

        $trustedProxies   = Request::getTrustedProxies();
        $trustedHeaderSet = Request::getTrustedHeaderSet();
        Request::setTrustedProxies(['10.0.0.2'], Request::HEADER_X_FORWARDED_FOR);

        try {
            // The last untrusted hop, the first entries are added by the client
            $this->assertSame('203.0.113.10', new AuroraIP()->ip($request));
        } finally {
            Request::setTrustedProxies($trustedProxies, $trustedHeaderSet);
        }
    }

    public function testIpIgnoresInvalidCloudflareHeader(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR'           => '173.245.48.10',
            'HTTP_CF_CONNECTING_IP' => '<script>alert(1)</script>',
        ]);

        $this->assertSame('173.245.48.10', new AuroraIP()->ip($request));
    }

    public function testIpReturnsTheCloudflareHeaderOfACloudflareRequest(): void
    {
        foreach (['173.245.48.10', '2606:4700::6810:84e5', '::ffff:162.158.1.1'] as $cloudflareIp) {
            $request = Request::create('/', 'GET', [], [], [], [
                'REMOTE_ADDR'           => $cloudflareIp,
                'HTTP_CF_CONNECTING_IP' => ' 203.0.113.20 ',
            ]);

            $this->assertSame('203.0.113.20', new AuroraIP()->ip($request));
        }
    }

    public function testIpReturnsTheCloudflareHeaderBehindATrustedProxy(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR'           => '10.0.0.2',
            'HTTP_X_FORWARDED_FOR'  => '162.158.1.1',
            'HTTP_CF_CONNECTING_IP' => '203.0.113.20',
        ]);

        $trustedProxies   = Request::getTrustedProxies();
        $trustedHeaderSet = Request::getTrustedHeaderSet();
        Request::setTrustedProxies(['10.0.0.2'], Request::HEADER_X_FORWARDED_FOR);

        try {
            $this->assertSame('203.0.113.20', new AuroraIP()->ip($request));
        } finally {
            Request::setTrustedProxies($trustedProxies, $trustedHeaderSet);
        }
    }

    public function testIpFallsBackWhenTheRequestHasNoClientIp(): void
    {
        $originalServer = $_SERVER;
        unset(
            $_SERVER['HTTP_CF_CONNECTING_IP'],
            $_SERVER['HTTP_X_FORWARDED_FOR'],
            $_SERVER['HTTP_CLIENT_IP'],
            $_SERVER['REMOTE_ADDR']
        );

        try {
            // Request::getClientIp() returns null without REMOTE_ADDR (e.g. CLI)
            $this->assertSame('127.0.0.1', new AuroraIP()->ip(new Request()));
        } finally {
            $_SERVER = $originalServer;
        }
    }
}
