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
     *
     * "php-auth-user", "php-auth-pw" and "php-auth-digest" are added by Symfony (ServerBag::getHeaders()) for HTTP Basic/Digest
     * authentication: "php-auth-pw" is the plain text password
     */
    private const array SENSITIVE_HEADERS = ['authorization', 'cookie', 'proxy-authorization', 'php-auth-user', 'php-auth-pw', 'php-auth-digest'];

    /**
     * Any other header whose name looks like it carries a credential (X-Api-Key, X-Auth-Token, X-CSRF-Token, X-Session-Id, ...)
     */
    private const string SENSITIVE_HEADER_PATTERN = '/auth|token|secret|passw|api[-_]?key|session|csrf|xsrf|cookie|signature/i';

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
                            'headers'   => $this->forwardableHeaders($request->headers->all()),
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

    /**
     * @param array<string, list<string|null>> $headers
     *
     * @return array<string, list<string|null>>
     */
    private function forwardableHeaders(array $headers): array
    {
        return array_filter(
            array_diff_key($headers, array_flip(self::SENSITIVE_HEADERS)),
            static fn(string $name): bool => !preg_match(self::SENSITIVE_HEADER_PATTERN, $name),
            ARRAY_FILTER_USE_KEY
        );
    }
}
