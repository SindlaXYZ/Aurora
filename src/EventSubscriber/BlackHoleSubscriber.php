<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\EventSubscriber;

use Psr\Log\LoggerInterface;
use Sindla\Bundle\AuroraBundle\Utils\AuroraIP\AuroraIP;
use Sindla\Bundle\AuroraBundle\Utils\AuroraStrink\AuroraStrink;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class BlackHoleSubscriber implements EventSubscriberInterface
{
    /**
     * Request headers that carry credentials and must never be forwarded to the BlackHole API
     */
    private const array SENSITIVE_HEADERS = ['authorization', 'cookie', 'proxy-authorization'];

    public function __construct(
        private AuroraIP            $auroraIP,
        private HttpClientInterface $httpClient,
        private ?LoggerInterface    $logger = null,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if ($event->isMainRequest()) {
            $this->auroraIP->ip($request);
            if (
                filter_var($_ENV['BLACK_HOLE_API_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN)
                && isset($_ENV['BLACK_HOLE_API_URL'])
                && str_starts_with($_ENV['BLACK_HOLE_API_URL'], 'http')
                && isset($_ENV['BLACK_HOLE_API_VERSION'])
                && !empty($_ENV['BLACK_HOLE_API_VERSION'])
                && isset($_ENV['BLACK_HOLE_API_ENDPOINT'])
                && !empty($_ENV['BLACK_HOLE_API_ENDPOINT'])
                && isset($_ENV['BLACK_HOLE_API_BEARER'])
                && !empty($_ENV['BLACK_HOLE_API_BEARER'])
            ) {
                $exception = $event->getThrowable();
                if ($exception instanceof NotFoundHttpException) {
                    try {
                        $payload = [
                            'method'    => $request->getMethod(),
                            'url'       => $event->getRequest()->getUri(),
                            'scheme'    => $request->getScheme(),
                            'host'      => $request->getHost(),
                            'port'      => $request->getPort() ?? 80,
                            'path'      => $request->getPathInfo(),
                            'query'     => $request->getQueryString(),
                            'isHTTP'    => boolval(!$request->isSecure()),
                            'isHTTPS'   => boolval($request->isSecure()),
                            'serverIP'  => $request->server->get('SERVER_ADDR'),
                            'clientIP'  => $this->auroraIP->ip($event->getRequest()),
                            'userAgent' => $request->headers->get('User-Agent'),
                            'headers'   => array_diff_key($request->headers->all(), array_flip(self::SENSITIVE_HEADERS)),
                        ];

                        $response = $this->httpClient->request(
                            'POST',
                            new AuroraStrink()->string(sprintf(
                                '%s/%s/%s',
                                $_ENV['BLACK_HOLE_API_URL'],
                                $_ENV['BLACK_HOLE_API_VERSION'],
                                $_ENV['BLACK_HOLE_API_ENDPOINT']
                            ))->compressSlashes()->__toString(),
                            [
                                'headers' => [
                                    'Content-Type'  => 'application/json',
                                    'Authorization' => 'Bearer ' . $_ENV['BLACK_HOLE_API_BEARER']
                                ],
                                'json'    => $payload,
                                'timeout' => 5,
                            ]
                        );

                        // Resolve the response here (throws on transport errors and 3xx-5xx): an unresolved response throws from its destructor
                        $response->getHeaders();
                    } catch (\Exception $e) {
                        // Reporting is best-effort: a failing BlackHole API must not turn the 404 into a 500
                        $this->logger?->warning('[AURORA] BlackHole API request failed: {message}', [
                            'message'   => $e->getMessage(),
                            'exception' => $e,
                        ]);
                    }
                }
            }
        }
    }
}
