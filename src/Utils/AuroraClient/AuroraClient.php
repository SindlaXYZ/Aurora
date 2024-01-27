<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraClient;

// Symfony
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

// GeoIp2
use GeoIp2\Database\Reader;

// Aurora
use Sindla\Bundle\AuroraBundle\Utils\AuroraMatch\AuroraMatch;

/**
 * Class Client
 *
 * @package AuroraBundle\Utils
 */
class AuroraClient
{
    private $container;

    private $geoLiteCountryReader;
    private $geoLiteCityReader;

    public function __construct(Container $Container)
    {
        $this->container = $Container;
    }

    private function readGeoLite2Country()
    {
        if (!$this->geoLiteCountryReader) {
            $GeoLite2CountryFile = $this->container->getParameter('aurora.resources') . '/maxmind-geoip2/GeoLite2Country.mmdb';
            if (!is_file($GeoLite2CountryFile)) {
                throw new \Exception("[{$GeoLite2CountryFile}] file not found!");
            }

            $this->geoLiteCountryReader = new Reader($GeoLite2CountryFile);
        }
    }

    private function readGeoLite2City()
    {
        if (!$this->geoLiteCityReader) {
            $GeoLite2CityFile = $this->container->getParameter('aurora.resources') . '/maxmind-geoip2/GeoLite2City.mmdb';
            if (!is_file($GeoLite2CityFile)) {
                throw new \Exception("[{$GeoLite2CityFile}] file not found!");
            }

            $this->geoLiteCityReader = new Reader($GeoLite2CityFile);
        }
    }

    /**
     * Read country code (ISO-) for an IP address
     *
     * @param string $ipAddress
     * @return string|null
     */
    public function ip2CountryCode(string $ipAddress)
    {
        $this->readGeoLite2Country();

        try {
            $record = $this->geoLiteCountryReader->country($ipAddress);
        } catch (\GeoIp2\Exception\AddressNotFoundException $e) {

        }

        return (isset($record)) ? $record->country->isoCode : null;
    }

    public function ip2CityCounty(string $ipAddress)
    {
        $this->readGeoLite2City();

        try {
            $record = $this->geoLiteCityReader->city($ipAddress);
        } catch (\GeoIp2\Exception\AddressNotFoundException $e) {

        }

        return (isset($record) && isset($record->subdivisions[0])) ? $record->subdivisions[0]->name : null;
    }

    public function ip2CityName(string $ipAddress)
    {
        $this->readGeoLite2City();

        try {
            $record = $this->geoLiteCityReader->city($ipAddress);
        } catch (\GeoIp2\Exception\AddressNotFoundException $e) {

        }

        return (isset($record)) ? $record->city->name : null;
    }

    /**
     * Return http:// or https://
     *
     * @return string
     */
    public function protocol()
    {
        // Reverse proxy
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            return $_SERVER['HTTP_X_FORWARDED_PROTO'] . '://';
        } else {
            return !empty($_SERVER['HTTPS']) ? "https://" : "http://";
        }
    }

    /**
     * Check is we have a SSL connection
     *
     * @return bool
     */
    public function isSSL(): bool
    {
        if (preg_match('/https/i', $this->protocol())) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns the client IP
     *
     * @param Request $request
     *
     * @return string
     */
    public function ip(Request $request): string
    {
        // CloudFlare: The real visitor IP addresses
        // https://support.cloudflare.com/hc/en-us/articles/200170986-How-does-Cloudflare-handle-HTTP-Request-headers-
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',') && $this->ipIsValide($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',')) {
            foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $ip) {
                $ip = trim($ip);
                if ($this->ipIsValide($ip)) {
                    return $ip;
                }
            }
        }

        if ($this->ipIsValide($request->getClientIp())) {
            return $request->getClientIp();
        }

        if (isset($_SERVER['HTTP_CLIENT_IP']) && $this->ipIsValide($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }

        if (isset($_SERVER['REMOTE_ADDR']) && $this->ipIsValide($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return '127.0.0.1';
    }

    /**
     * Check if an IP is valid
     *
     * @param $ip
     *
     * @return bool
     */
    public function ipIsValide($ip): bool
    {
        $ipIsValid = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        return (($ipIsValid === false) ? false : true);
    }

    /**
     * Return the client browser preferred languages
     *
     * @return array
     */
    public function preferredLanguages(): array
    {
        $prefLanguages = [];

        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $prefLanguages = array_reduce(
                explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']),
                function ($res, $el) {
                    [$l, $q] = array_merge(explode(';q=', $el), [1]);
                    $res[$l] = (float)$q;
                    return $res;
                }, []);
            arsort($prefLanguages);
        }

        return $prefLanguages;
    }

    /**
     * Check if an IPv4 is a Google Bot (by hostname)
     *
     * @param string $IP
     *
     * @return bool
     */
    public function ipIsGoogleBot($IP): bool
    {
        if ($IP instanceof Request) {
            trigger_error('Method ' . __METHOD__ . ' with Request as parameter is deprecated. Use client Address IP (string) instead.', E_USER_DEPRECATED);
            $IP = $this->ip($IP);
        }

        $hostname = gethostbyaddr(trim($IP));

        /** @var AuroraMatch $AuroraMatch */
        $AuroraMatch = new AuroraMatch();

        return $AuroraMatch->matchAtLeastOneDomain($hostname, ['google.com', 'googlebot.com']);
    }

    /**
     * Check if an IPv4 is a Microsoft/Bing bot (by hostname)
     *
     * @param string $IP
     *
     * @return bool
     */
    public function ipIsBingBot($IP): bool
    {
        if ($IP instanceof Request) {
            trigger_error('Method ' . __METHOD__ . ' with Request as parameter is deprecated. Use client Address IP (string) instead.', E_USER_DEPRECATED);
            $IP = $this->ip($IP);
        }

        $hostname = gethostbyaddr(trim($IP));

        /** @var AuroraMatch $AuroraMatch */
        $AuroraMatch = new AuroraMatch();

        return $AuroraMatch->matchAtLeastOneDomain($hostname, ['msn.com', 'bing.com']);
    }
}
