<?php

declare(strict_types=1);

namespace Symfony\Component\HttpFoundation {
    if (!class_exists(Request::class)) {
        class Request
        {
            public function __construct(
                array $query = [],
                array $request = [],
                array $attributes = [],
                array $cookies = [],
                array $files = [],
                array $server = [],
                $content = null
            ) {
                $this->server = $server;
            }

            public function getClientIp(): ?string
            {
                return $this->server['REMOTE_ADDR'] ?? null;
            }
        }
    }
}

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraClient {
    use PHPUnit\Framework\TestCase;
    use Sindla\Bundle\AuroraBundle\Utils\AuroraClient\AuroraClient;
    use Symfony\Component\HttpFoundation\Request;

    class AuroraClientBotTest extends TestCase
    {
        public function testIpIsGoogleBotAcceptsRequest(): void
        {
            $client = new AuroraClient();
            $ip = '127.0.0.1';
            $this->assertSame(
                $client->ipIsGoogleBot($ip),
                @$client->ipIsGoogleBot(new Request([], [], [], [], [], ['REMOTE_ADDR' => $ip]))
            );
        }

        public function testIpIsBingBotAcceptsRequest(): void
        {
            $client = new AuroraClient();
            $ip = '127.0.0.1';
            $this->assertSame(
                $client->ipIsBingBot($ip),
                @$client->ipIsBingBot(new Request([], [], [], [], [], ['REMOTE_ADDR' => $ip]))
            );
        }

        public function testIpIsGoogleOrBingBotAcceptsRequest(): void
        {
            $client = new AuroraClient();
            $ip = '127.0.0.1';
            $this->assertSame(
                $client->ipIsGoogleOrBingBot($ip),
                @$client->ipIsGoogleOrBingBot(new Request([], [], [], [], [], ['REMOTE_ADDR' => $ip]))
            );
        }
    }
}
