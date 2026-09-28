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
            ['999.999.999.999', false],
            ['10.0.0.1', true],
            ['fd12:3456::1', true],
            ['::1', true],
            ['66.249.66.1', false],
            ['2606:4700::1111', false],
        ];
    }

    #[DataProvider('dataIsPublic')]
    public function testIsPublic(string $given, bool $expectedPublic, bool $expectedPublicIPV6): void
    {
        $auroraIP = new AuroraIP();

        $this->assertSame($expectedPublic, $auroraIP->isPublic($given));
        $this->assertSame($expectedPublicIPV6, $auroraIP->isPublicIPV6($given));
    }

    public static function dataIsPublic(): array
    {
        return [
            'public IPv4'     => ['66.249.66.1', true, false],
            'public IPv6'     => ['2606:4700::1111', true, true],
            'private IPv4'    => ['10.0.0.1', false, false],
            'loopback IPv4'   => ['127.0.0.1', false, false],
            'unique local'    => ['fd12:3456::1', false, false],
            'link-local IPv6' => ['fe80::1', false, false],
            'loopback IPv6'   => ['::1', false, false],
            'invalid'         => ['not-an-ip', false, false],
        ];
    }

    #[DataProvider('dataIsCloudflare')]
    public function testIsCloudflare(string $given, bool $expected): void
    {
        $this->assertSame($expected, new AuroraIP()->isCloudflare($given));
    }

    public static function dataIsCloudflare(): array
    {
        return [
            'IPv4 edge server'             => ['104.16.0.1', true],
            'IPv6 edge server'             => ['2a06:98c0::1', true],
            'IPv4-mapped IPv6 edge server' => ['::ffff:104.16.0.1', true],
            'another network'              => ['192.0.2.1', false],
            'another IPv6 network'         => ['2001:db8::1', false],
            'invalid'                      => ['not-an-ip', false],
            'empty'                        => ['', false],
        ];
    }

    /**
     * Positive IPs taken from the ranges of KnownBotsAndCrawlers, negative ones next to them
     */
    #[DataProvider('dataKnownBotsAndCrawlers')]
    public function testKnownBotsAndCrawlers(string $method, string $given, bool $expected): void
    {
        $auroraIP = new AuroraIP();

        $this->assertSame($expected, $auroraIP->{$method}($given), sprintf('%s("%s")', $method, $given));
        // Any known bot is a bot
        $this->assertSame($expected, $auroraIP->isBot($given), sprintf('isBot("%s")', $given));
    }

    public static function dataKnownBotsAndCrawlers(): array
    {
        return [
            'Google IPv6'                       => ['isGoogle', '2001:4860:4801:10::1', true],
            'Google, invalid'                   => ['isGoogle', 'not-an-ip', false],
            'Bing'                              => ['isBing', '157.55.39.1', true],
            'Bing, other range'                 => ['isBing', '40.77.167.90', true],
            'Bing, next network'                => ['isBing', '157.55.40.1', false],
            'Bing, invalid'                     => ['isBing', 'not-an-ip', false],
            'Apple'                             => ['isApple', '17.241.219.5', true],
            'Apple, other range'                => ['isApple', '17.22.237.10', true],
            'Apple, outside the ranges'         => ['isApple', '17.0.0.1', false],
            'Apple, invalid'                    => ['isApple', 'not-an-ip', false],
            'OpenAI, first IP of a /28'         => ['isOpenAI', '104.210.139.192', true],
            'OpenAI, last IP of a /28'          => ['isOpenAI', '104.210.139.207', true],
            'OpenAI, after a /28'               => ['isOpenAI', '104.210.139.208', false],
            'OpenAI, invalid'                   => ['isOpenAI', 'not-an-ip', false],
            'UptimeRobot, IPv4 range'           => ['isUpTimeRobot', '69.162.124.230', true],
            'UptimeRobot, after the IPv4 range' => ['isUpTimeRobot', '69.162.124.240', false],
            'UptimeRobot, IPv6 range'           => ['isUpTimeRobot', '2607:ff68:107::10', true],
            'UptimeRobot, after the IPv6 range' => ['isUpTimeRobot', '2607:ff68:107::80', false],
            'UptimeRobot, invalid'              => ['isUpTimeRobot', 'not-an-ip', false],
            'documentation IPv4'                => ['isGoogle', '192.0.2.1', false],
            'documentation IPv6'                => ['isBing', '2001:db8::1', false],
        ];
    }

    public function testGoogleIsNotAnotherBot(): void
    {
        $auroraIP = new AuroraIP();

        $this->assertTrue($auroraIP->isGoogle('66.249.69.69'));
        $this->assertFalse($auroraIP->isBing('66.249.69.69'));
        $this->assertFalse($auroraIP->isApple('66.249.69.69'));
        $this->assertFalse($auroraIP->isOpenAI('66.249.69.69'));
        $this->assertFalse($auroraIP->isUpTimeRobot('66.249.69.69'));
        $this->assertTrue($auroraIP->isBot('66.249.69.69'));
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
            ['198.51.100.200', '198.51.96.0/20', true],
            ['198.51.112.1', '198.51.96.0/20', false],
            ['203.0.113.77', '0.0.0.0/0', true],
            ['2001:db8::1', '::/0', true],
            ['192.168.1.5', '192.168.1.0/abc', false],
            ['192.168.1.5', '192.168.1.0/-1', false],
            ['192.168.1.5', '192.168.1.0/', false],
            ['192.168.1.5', '/24', false],
            ['192.168.1.5', '2001:db8::/32', false],
            ['2001:db8::1', '192.168.1.0/24', false],
            ['not-an-ip', '192.168.1.0/24', false],
            ['192.168.1.5', 'not-a-subnet/24', false],
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

    public function testIpFallsBackToTheRemoteAddressOfTheServer(): void
    {
        $originalServer = $_SERVER;

        try {
            // e.g. a sub-request created with "new Request()"
            $_SERVER['REMOTE_ADDR'] = '192.0.2.44';
            $this->assertSame('192.0.2.44', new AuroraIP()->ip(new Request()));

            // An invalid client IP is not returned
            $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => 'not-an-ip']);
            $this->assertSame('192.0.2.44', new AuroraIP()->ip($request));

            $_SERVER['REMOTE_ADDR'] = 'not-an-ip';
            $this->assertSame('127.0.0.1', new AuroraIP()->ip($request));
        } finally {
            $_SERVER = $originalServer;
        }
    }
}
