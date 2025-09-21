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
        ];
    }
}
