<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\EventSubscriber\BlackHoleSubscriber;
use Sindla\Bundle\AuroraBundle\Utils\AuroraClient\AuroraClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class BlackHoleSubscriberTest extends TestCase
{
    public function testUsesConfiguredBearerTokenWhenForwardingRequest(): void
    {
        $previousEnv = [];
        foreach ([
            'BLACK_HOLE_API_ENABLED',
            'BLACK_HOLE_API_URL',
            'BLACK_HOLE_API_VERSION',
            'BLACK_HOLE_API_ENDPOINT',
            'BLACK_HOLE_API_BEARER',
        ] as $key) {
            $previousEnv[$key] = $_ENV[$key] ?? null;
        }

        $_ENV['BLACK_HOLE_API_ENABLED'] = 'true';
        $_ENV['BLACK_HOLE_API_URL'] = 'https://blackhole.example';
        $_ENV['BLACK_HOLE_API_VERSION'] = 'v1';
        $_ENV['BLACK_HOLE_API_ENDPOINT'] = 'events';
        $_ENV['BLACK_HOLE_API_BEARER'] = 'test-bearer';

        $capturedOptions = null;
        $mockClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$capturedOptions): MockResponse {
                $capturedOptions = [
                    'method'  => $method,
                    'url'     => $url,
                    'options' => $options,
                ];

                return new MockResponse('', ['http_code' => 200]);
            }
        );

        $auroraClient = $this->createMock(AuroraClient::class);
        $auroraClient
            ->expects($this->exactly(2))
            ->method('ip')
            ->willReturn('203.0.113.10');

        $subscriber = new BlackHoleSubscriber($auroraClient, $mockClient);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('https://app.example/resource');
        $request->server->set('SERVER_ADDR', '198.51.100.20');
        $event = new ExceptionEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new NotFoundHttpException()
        );

        try {
            $subscriber->onKernelException($event);
        } finally {
            foreach ($previousEnv as $key => $value) {
                if (null === $value) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $value;
                }
            }
        }

        $this->assertNotNull($capturedOptions, 'The HTTP client was not invoked.');
        $this->assertSame('POST', $capturedOptions['method']);
        $this->assertArrayHasKey('normalized_headers', $capturedOptions['options']);
        $this->assertArrayHasKey('authorization', $capturedOptions['options']['normalized_headers']);
        $this->assertContains(
            'Authorization: Bearer test-bearer',
            $capturedOptions['options']['normalized_headers']['authorization']
        );
    }
}
