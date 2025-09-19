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
}
