<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraClient;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraClient\AuroraClient;

final class AuroraClientProtocolTest extends TestCase
{
    private array $originalServer;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
    }

    public function testProtocolUsesFirstForwardedProtoValue(): void
    {
        $client = new AuroraClient();

        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https, http';
        self::assertSame('https://', $client->protocol());

        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'HTTP,https';
        self::assertSame('http://', $client->protocol());
    }

    public function testProtocolSkipsEmptyForwardedProtoEntries(): void
    {
        $client = new AuroraClient();

        $_SERVER['HTTP_X_FORWARDED_PROTO'] = '  , https ';
        self::assertSame('https://', $client->protocol());
    }

    public function testProtocolKeepsOnlyTheSchemeOfAForwardedProtoUrl(): void
    {
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'HTTPS://proxy.example.com';

        self::assertSame('https://', new AuroraClient()->protocol());
    }

    public function testProtocolFallsBackToTheHttpsServerVariable(): void
    {
        $client = new AuroraClient();

        // Only empty entries: the reverse proxy header gives no protocol
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = ' , ';
        $_SERVER['HTTPS']                  = 'on';
        self::assertSame('https://', $client->protocol());

        // Not a string: ignored
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = ['http'];
        self::assertSame('https://', $client->protocol());

        unset($_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTPS']);
        self::assertSame('http://', $client->protocol());
        self::assertFalse($client->isSSL());
    }
}
