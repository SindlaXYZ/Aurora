<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraClient;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraClient\AuroraClient;
use Symfony\Component\HttpFoundation\Request;

class AuroraClientIpTest extends TestCase
{
    public function testIpTrimsSingleForwardedHeader(): void
    {
        $request = Request::create(
            '/',
            'GET',
            [],
            [],
            [],
            ['REMOTE_ADDR' => '198.51.100.5']
        );

        $originalServer = $_SERVER;
        $_SERVER['HTTP_X_FORWARDED_FOR'] = ' 203.0.113.10 ';

        $client = new AuroraClient();

        try {
            $this->assertSame('203.0.113.10', $client->ip($request));
        } finally {
            $_SERVER = $originalServer;
        }
    }
}
