<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraClient;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraClient\AuroraClient;

class AuroraClientIpValidationTest extends TestCase
{
    #[DataProvider('provideDocumentationIps')]
    public function testIpIsValidAllowsDocumentationRanges(string $ip): void
    {
        $client = new AuroraClient();

        $this->assertTrue(
            $client->ipIsValid($ip),
            sprintf('Expected documentation IP %s to be considered valid.', $ip)
        );
    }

    public static function provideDocumentationIps(): array
    {
        return [
            'TEST-NET-1' => ['192.0.2.10'],
            'TEST-NET-2' => ['198.51.100.23'],
            'TEST-NET-3' => ['203.0.113.42'],
            'IPv6 documentation prefix' => ['2001:db8::1'],
        ];
    }

    #[DataProvider('provideInvalidIps')]
    public function testIpIsValidStillRejectsLocalAddresses(string $ip): void
    {
        $client = new AuroraClient();

        $this->assertFalse(
            $client->ipIsValid($ip),
            sprintf('Expected %s to be considered invalid.', $ip)
        );
    }

    public static function provideInvalidIps(): array
    {
        return [
            'empty string' => [''],
            'loopback IPv6' => ['::1'],
            'loopback IPv4' => ['127.0.0.1'],
            'not an IP' => ['not-an-ip'],
            'private IPv4' => ['10.1.2.3'],
            'reserved IPv4' => ['240.0.0.1'],
            'private IPv6' => ['fd00::1'],
        ];
    }

    public function testIpIsValidAcceptsAPublicIpv4SurroundedByWhitespace(): void
    {
        $this->assertTrue(new AuroraClient()->ipIsValid(' 8.8.8.8 '));
    }

    #[DataProvider('provideNonStringValues')]
    public function testIpIsValidRejectsNonStringValues(mixed $ip): void
    {
        $this->assertFalse(new AuroraClient()->ipIsValid($ip));
    }

    public static function provideNonStringValues(): array
    {
        return [
            'null'                      => [null],
            'array'                     => [['192.0.2.10']],
            'object'                    => [new \stdClass()],
            'boolean'                   => [true],
            'integer form of 192.0.2.1' => [3221225985],
        ];
    }

    #[DataProvider('provideCidrMatches')]
    public function testIpMatchesCidr(string $ip, string $cidr, bool $expected): void
    {
        $client = new AuroraClient();

        $this->assertSame($expected, new \ReflectionMethod($client, 'ipMatchesCidr')->invoke($client, $ip, $cidr));
    }

    public static function provideCidrMatches(): array
    {
        return [
            'no prefix separator'            => ['192.0.2.1', '192.0.2.0', false],
            'empty subnet'                   => ['192.0.2.1', '/24', false],
            'empty prefix length'            => ['192.0.2.1', '192.0.2.0/', false],
            'invalid subnet'                 => ['192.0.2.1', 'not-a-subnet/24', false],
            'IPv4 against an IPv6 subnet'    => ['192.0.2.1', '2001:db8::/32', false],
            'prefix longer than the address' => ['192.0.2.1', '192.0.2.0/33', false],
            '/0 matches every address'       => ['203.0.113.9', '0.0.0.0/0', true],
            '/23 spans two /24 networks'     => ['192.0.3.200', '192.0.2.0/23', true],
            '/25 excludes the upper half'    => ['192.0.2.200', '192.0.2.0/25', false],
            '/25 includes the lower half'    => ['192.0.2.127', '192.0.2.0/25', true],
            '/32 is a single address'        => ['192.0.2.1', '192.0.2.1/32', true],
            'IPv6 /33 includes'              => ['2001:db8:7fff::1', '2001:db8::/33', true],
            'IPv6 /33 excludes'              => ['2001:db8:8000::1', '2001:db8::/33', false],
        ];
    }
}
