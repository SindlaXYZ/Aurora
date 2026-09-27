<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraMonolog;

use Monolog\LogRecord;
use Sindla\Bundle\AuroraBundle\Utils\AuroraClient\AuroraClient;
use Sindla\Bundle\AuroraBundle\Utils\AuroraIP\AuroraIP;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Monolog 3 processor: processors receive and must return a LogRecord object (not an array).
 * The client details are added to $record->extra['misc'] (rendered by HtmlFormatter).
 */
class MiscProcessor
{
    private Container     $container;
    private RequestStack  $requestStack;
    private ?string       $cachedClientIp = null;
    private ?AuroraClient $auroraClient   = null;
    private LogRecord     $record;

    public function __construct(Container $container, RequestStack $requestStack)
    {
        $this->container    = $container;
        $this->requestStack = $requestStack;
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $this->record = $record;

        $this->clientDetails();

        return $this->record;
    }

    /**
     * Set the client details
     *
     * @throws \Exception
     */
    public function clientDetails(): void
    {
        // Ensure we have a request (maybe we're in a console command)
        if ($request = $this->requestStack->getCurrentRequest()) {
            $ip = new AuroraIP()->ip($request);

            // Misc records
            $this->record->extra['misc'] = [
                'IP'      => $ip,
                'Country' => $this->auroraClient()->ip2CountryCode($ip),
                'U/A'     => $request->headers->get('User-Agent'),
            ];
        }
    }

    public function extra(): LogRecord
    {
        // request_ip will hold our proxy server's IP
        $this->record->extra['request_ip'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unavailable';

        // client_ip will hold the request's actual origin address
        $this->record->extra['client_ip'] = $this->cachedClientIp ? $this->cachedClientIp : 'unavailable';

        // Return if we already know client's IP
        if ($this->record->extra['client_ip'] !== 'unavailable') {
            return $this->record;
        }

        // Ensure we have a request (maybe we're in a console command)
        if (!$request = $this->requestStack->getCurrentRequest()) {
            return $this->record;
        }

        // If we do, get the client's IP, and cache it for later.
        $this->cachedClientIp             = $request->getClientIp();
        $this->record->extra['client_ip'] = $this->cachedClientIp ?? 'unavailable';

        return $this->record;
    }

    /**
     * The "aurora.client" service is optional (it is no longer registered by the bundle), fall back to a local instance.
     */
    private function auroraClient(): AuroraClient
    {
        $auroraClient = $this->container->has('aurora.client') ? $this->container->get('aurora.client') : null;

        return $auroraClient instanceof AuroraClient ? $auroraClient : ($this->auroraClient ??= new AuroraClient($this->container));
    }
}
