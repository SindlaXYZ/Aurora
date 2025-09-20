<?php

declare(strict_types=1);


namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraClient;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraClient\AuroraClient;
use Symfony\Component\HttpFoundation\Request;

class AuroraClientBotTest extends TestCase
{
    public function testIpIsGoogleBotAcceptsRequest(): void
    {
        $client = new AuroraClient();
        $ip     = '127.0.0.1';
        $this->assertSame(
            $client->ipIsGoogleBot($ip),
            @$client->ipIsGoogleBot(new Request([], [], [], [], [], ['REMOTE_ADDR' => $ip]))
        );
    }

    public function testIpIsBingBotAcceptsRequest(): void
    {
        $client = new AuroraClient();
        $ip     = '127.0.0.1';
        $this->assertSame(
            $client->ipIsBingBot($ip),
            @$client->ipIsBingBot(new Request([], [], [], [], [], ['REMOTE_ADDR' => $ip]))
        );
    }

    public function testIpIsGoogleOrBingBotAcceptsRequest(): void
    {
        $client = new AuroraClient();
        $ip     = '127.0.0.1';
        $this->assertSame(
            $client->ipIsGoogleOrBingBot($ip),
            @$client->ipIsGoogleOrBingBot(new Request([], [], [], [], [], ['REMOTE_ADDR' => $ip]))
        );
    }
}

