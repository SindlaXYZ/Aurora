<?php

namespace Sindla\Bundle\AuroraBundle\Service\Monolog;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;

class MiscProcessor
{
    private $container;
    private $requestStack;
    private $cachedClientIp = null;
    private $record;

    public function __construct(Container $container, RequestStack $requestStack)
    {
        $this->container    = $container;
        $this->requestStack = $requestStack;
    }

    public function __invoke(array $record)
    {
        $this->record = $record;

        $this->clientDetails();

        return $this->record;
    }

    /**
     * Set the client details
     *
     * @throws \Exception
     * @return  none
     */
    public function clientDetails()
    {
        // Ensure we have a request (maybe we're in a console command)
        if ($request = $this->requestStack->getCurrentRequest()) {
            $auroraClient = $this->container->get('aurora.client');
            $ip           = $auroraClient->ip($request);

            // Misc records
            $this->record['misc']['IP']      = $ip;
            $this->record['misc']['Country'] = $auroraClient->ip2CountryCode($ip);
            $this->record['misc']['U/A']     = $request->headers->get('User-Agent');
        }
    }

    public function extra()
    {
        // request_ip will hold our proxy server's IP
        $this->record['extra']['request_ip'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unavailable';

        // client_ip will hold the request's actual origin address
        $this->record['extra']['client_ip'] = $this->cachedClientIp ? $this->cachedClientIp : 'unavailable';

        // Return if we already know client's IP
        if ($this->record['extra']['client_ip'] !== 'unavailable') {
            return $this->record;
        }

        // Ensure we have a request (maybe we're in a console command)
        if (!$request = $this->requestStack->getCurrentRequest()) {
            return $this->record;
        }

        // If we do, get the client's IP, and cache it for later.
        $this->cachedClientIp               = $request->getClientIp();
        $this->record['extra']['client_ip'] = $this->cachedClientIp;

        return $this->record;
    }
}