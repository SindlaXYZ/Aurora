<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sindla\Bundle\AuroraBundle\EventSubscriber\BlackHoleSubscriber;
use Sindla\Bundle\AuroraBundle\Utils\AuroraIP\AuroraIP;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class BlackHoleSubscriberTest extends TestCase
{
    private const array API_ENV = [
        'BLACK_HOLE_API_ENABLED'  => 'true',
        'BLACK_HOLE_API_URL'      => 'https://blackhole.example',
        'BLACK_HOLE_API_VERSION'  => 'v1',
        'BLACK_HOLE_API_ENDPOINT' => 'events',
        'BLACK_HOLE_API_BEARER'   => 'test-bearer',
    ];

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

        $auroraIP = $this->createMock(AuroraIP::class);
        $auroraIP
            ->expects($this->exactly(2))
            ->method('ip')
            ->willReturn('203.0.113.10');

        $subscriber = new BlackHoleSubscriber($auroraIP, $mockClient);

        $kernel = $this->createStub(HttpKernelInterface::class);
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

    public function testDoesNotForwardCredentialHeaders(): void
    {
        $capturedBody = null;
        $mockClient   = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$capturedBody): MockResponse {
                $capturedBody = $options['body'];

                return new MockResponse('', ['http_code' => 200]);
            }
        );

        $request = Request::create('https://app.example/resource');
        $request->headers->set('Cookie', 'PHPSESSID=secret-session-id');
        $request->headers->set('Authorization', 'Bearer secret-user-token');
        $request->headers->set('X-Custom', 'kept');

        $this->dispatchNotFound(new BlackHoleSubscriber($this->createStub(AuroraIP::class), $mockClient), $request);

        $this->assertIsString($capturedBody, 'The HTTP client was not invoked.');
        $payload = json_decode($capturedBody, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('cookie', $payload['headers']);
        $this->assertArrayNotHasKey('authorization', $payload['headers']);
        $this->assertSame(['kept'], $payload['headers']['x-custom']);
    }

    public function testDoesNotForwardBasicAuthCredentialsNorTokenHeaders(): void
    {
        $capturedBody = null;
        $mockClient   = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$capturedBody): MockResponse {
                $capturedBody = $options['body'];

                return new MockResponse('', ['http_code' => 200]);
            }
        );

        // Symfony exposes HTTP Basic credentials as the "php-auth-user" / "php-auth-pw" headers: the plain text password used to be sent
        $request = Request::create('https://app.example/resource', server: ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'plain-text-password']);
        $request->headers->set('X-Api-Key', 'secret-api-key');
        $request->headers->set('X-Auth-Token', 'secret-auth-token');
        $request->headers->set('X-CSRF-Token', 'secret-csrf-token');
        $request->headers->set('X-Custom', 'kept');

        $this->dispatchNotFound(new BlackHoleSubscriber($this->createStub(AuroraIP::class), $mockClient), $request);

        $this->assertIsString($capturedBody, 'The HTTP client was not invoked.');
        $this->assertStringNotContainsString('plain-text-password', $capturedBody);
        $this->assertStringNotContainsString('secret-', $capturedBody);

        $payload = json_decode($capturedBody, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['kept'], $payload['headers']['x-custom']);
        $this->assertArrayHasKey('host', $payload['headers']);
    }

    #[DataProvider('dataFailingApiResponses')]
    public function testFailingApiDoesNotBreakTheNotFoundResponse(MockResponse $apiResponse): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $subscriber = new BlackHoleSubscriber($this->createStub(AuroraIP::class), new MockHttpClient($apiResponse), $logger);

        // Used to rethrow the HttpClient exception from the kernel.exception listener (every 404 became a 500)
        $this->dispatchNotFound($subscriber, Request::create('https://app.example/resource'));
    }

    public static function dataFailingApiResponses(): array
    {
        return [
            'server error'    => [new MockResponse('', ['http_code' => 500])],
            'transport error' => [new MockResponse('', ['error' => 'Connection refused'])],
        ];
    }

    public function testTheNotFoundExceptionDispatchedByTheKernelIsReported(): void
    {
        $captured   = null;
        $mockClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$captured): MockResponse {
                $captured = ['url' => $url, 'payload' => json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR)];

                return new MockResponse('', ['http_code' => 200]);
            }
        );

        $auroraIP = $this->createStub(AuroraIP::class);
        $auroraIP->method('ip')->willReturn('203.0.113.10');

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new BlackHoleSubscriber($auroraIP, $mockClient));

        $request = Request::create('https://app.example/missing?page=2', server: ['SERVER_ADDR' => '198.51.100.20']);
        $request->headers->set('User-Agent', 'ExampleBot/1.0');
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new NotFoundHttpException());

        // The slashes around the configured URL parts are compressed, the "https://" of the URL is kept
        $this->withApiEnv(
            ['BLACK_HOLE_API_URL' => 'https://blackhole.example/', 'BLACK_HOLE_API_VERSION' => '/v1/'],
            static fn() => $dispatcher->dispatch($event, KernelEvents::EXCEPTION)
        );

        $this->assertNotNull($captured, 'The HTTP client was not invoked.');
        $this->assertSame('https://blackhole.example/v1/events', $captured['url']);

        unset($captured['payload']['headers']);
        $this->assertSame([
            'method'    => 'GET',
            'url'       => 'https://app.example/missing?page=2',
            'scheme'    => 'https',
            'host'      => 'app.example',
            'port'      => 443,
            'path'      => '/missing',
            'query'     => 'page=2',
            'isHTTP'    => false,
            'isHTTPS'   => true,
            'serverIP'  => '198.51.100.20',
            'clientIP'  => '203.0.113.10',
            'userAgent' => 'ExampleBot/1.0',
        ], $captured['payload']);
    }

    #[DataProvider('dataNotReported')]
    public function testIsNotReported(array $env, int $requestType, \Throwable $exception): void
    {
        $mockClient = new MockHttpClient(new MockResponse('', ['http_code' => 200]));
        $subscriber = new BlackHoleSubscriber($this->createStub(AuroraIP::class), $mockClient);
        $event      = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('https://app.example/resource'),
            $requestType,
            $exception
        );

        $this->withApiEnv($env, static fn() => $subscriber->onKernelException($event));

        $this->assertSame(0, $mockClient->getRequestsCount());
    }

    public static function dataNotReported(): iterable
    {
        $main     = HttpKernelInterface::MAIN_REQUEST;
        $notFound = new NotFoundHttpException();

        yield 'sub-request' => [[], HttpKernelInterface::SUB_REQUEST, $notFound];
        yield 'not a 404' => [[], $main, new AccessDeniedHttpException()];
        yield 'not an HTTP exception' => [[], $main, new \RuntimeException('Aurora')];
        yield 'API disabled' => [['BLACK_HOLE_API_ENABLED' => 'false'], $main, $notFound];
        yield 'API enabled flag missing' => [['BLACK_HOLE_API_ENABLED' => null], $main, $notFound];
        yield 'URL without scheme' => [['BLACK_HOLE_API_URL' => 'blackhole.example'], $main, $notFound];
        yield 'URL missing' => [['BLACK_HOLE_API_URL' => null], $main, $notFound];
        yield 'empty version' => [['BLACK_HOLE_API_VERSION' => ''], $main, $notFound];
        yield 'empty endpoint' => [['BLACK_HOLE_API_ENDPOINT' => ''], $main, $notFound];
        yield 'bearer missing' => [['BLACK_HOLE_API_BEARER' => null], $main, $notFound];
    }

    private function dispatchNotFound(BlackHoleSubscriber $subscriber, Request $request): void
    {
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new NotFoundHttpException()
        );

        $this->withApiEnv([], static fn() => $subscriber->onKernelException($event));
    }

    /**
     * Runs the callback with the BlackHole API environment variables, then restores them
     *
     * @param array<string, string|null> $overrides A null value removes the variable
     */
    private function withApiEnv(array $overrides, callable $callback): void
    {
        $previousEnv = [];
        foreach ($overrides + self::API_ENV as $key => $value) {
            $previousEnv[$key] = $_ENV[$key] ?? null;

            if (null === $value) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }

        try {
            $callback();
        } finally {
            foreach ($previousEnv as $key => $value) {
                if (null === $value) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $value;
                }
            }
        }
    }
}
