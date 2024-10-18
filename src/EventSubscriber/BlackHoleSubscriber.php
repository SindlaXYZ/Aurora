<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\EventSubscriber;

use Sindla\Bundle\AuroraBundle\Utils\AuroraClient\AuroraClient;
use Sindla\Bundle\AuroraBundle\Utils\Strink\Strink;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

readonly class BlackHoleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AuroraClient $auroraClient
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
        $this->auroraClient->ip($event->getRequest());
        if (isset($_ENV['BLACK_HOLE_API_HOST']) && str_starts_with($_ENV['BLACK_HOLE_API_HOST'], 'http') && isset($_ENV['BLACK_HOLE_API_BEARER']) && !empty($_ENV['BLACK_HOLE_API_BEARER'])) {
            $exception = $event->getThrowable();
            if ($exception instanceof NotFoundHttpException) {
                $client = HttpClient::create();
                try {
                    $request = $event->getRequest();
                    $payload = [
                        'method'    => $request->getMethod(),
                        'url'       => $event->getRequest()->getUri(),
                        'scheme'    => $request->getScheme(),
                        'host'      => $request->getHost(),
                        'port'      => $request->getPort(),
                        'path'      => $request->getPathInfo(),
                        'query'     => $request->getQueryString(),
                        'isHTTP'    => intval(!$request->isSecure()),
                        'isHTTPS'   => intval($request->isSecure()),
                        'serverIP'  => $request->server->get('SERVER_ADDR'),
                        'clientIP'  => $this->auroraClient->ip($event->getRequest()),
                        'userAgent' => $request->headers->get('User-Agent'),
                        'headers'   => $request->headers->all(),
                    ];

                    $client->request(
                        'POST',
                        (new Strink())->string($_ENV['BLACK_HOLE_API_HOST'] . '/api/paths')->compressSlashes()->__toString(),
                        [
                            'headers' => [
                                'Content-Type'  => 'application/json',
                                'Authorization' => 'Bearer ' . $_ENV['BLACK_HOLE_API_BEARER']
                            ],
                            'json'    => $payload,
                        ]
                    );
                } catch (\Exception|TransportExceptionInterface $e) {
                    // do nothing
                }
            }
        }
    }
}
