<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraClient;

use GeoIp2\Database\Reader;
use Sindla\Bundle\AuroraBundle\Utils\AuroraMatch\AuroraMatch;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

class AuroraClient
{
    private $geoLiteCountryReader;
    private $geoLiteCityReader;
    private $geoLiteASNReader;

    public function __construct(
        private ?Container $container = null
    )
    {
    }

    private function readGeoLite2Country(): void
    {
        if (null == $this->container) {
            throw new \Exception('Container not set/initialized!');
        }

        if (!$this->geoLiteCountryReader) {
            $GeoLite2CountryFile = $this->container->getParameter('aurora.resources') . '/maxmind-geoip2/GeoLite2Country.mmdb';
            if (!is_file($GeoLite2CountryFile)) {
                throw new \Exception("[{$GeoLite2CountryFile}] file not found!");
            }

            $this->geoLiteCountryReader = new Reader($GeoLite2CountryFile);
        }
    }

    private function readGeoLite2City(): void
    {
        if (null == $this->container) {
            throw new \Exception('Container not set/initialized!');
        }

        if (!$this->geoLiteCityReader) {
            $GeoLite2CityFile = $this->container->getParameter('aurora.resources') . '/maxmind-geoip2/GeoLite2City.mmdb';
            if (!is_file($GeoLite2CityFile)) {
                throw new \Exception("[{$GeoLite2CityFile}] file not found!");
            }

            $this->geoLiteCityReader = new Reader($GeoLite2CityFile);
        }
    }

    private function readGeoLite2ASN(): void
    {
        if (null == $this->container) {
            throw new \Exception('Container not set/initialized!');
        }

        if (!$this->geoLiteASNReader) {
            $GeoLite2ASNFile = $this->container->getParameter('aurora.resources') . '/maxmind-geoip2/GeoLite2ASN.mmdb';
            if (!is_file($GeoLite2ASNFile)) {
                throw new \Exception("[{$GeoLite2ASNFile}] file not found!");
            }

            $this->geoLiteASNReader = new Reader($GeoLite2ASNFile);
        }
    }

    /**
     * Read country code (ISO-) for an IP address
     */
    public function ip2CountryCode(string $ipAddress): ?string
    {
        $this->readGeoLite2Country();

        try {
            $record = $this->geoLiteCountryReader->country($ipAddress);
        } catch (\GeoIp2\Exception\AddressNotFoundException $e) {

        }

        return (isset($record)) ? $record->country->isoCode : null;
    }

    public function ip2CityCounty(string $ipAddress): ?string
    {
        $this->readGeoLite2City();

        try {
            $record = $this->geoLiteCityReader->city($ipAddress);
        } catch (\GeoIp2\Exception\AddressNotFoundException $e) {

        }

        return (isset($record) && isset($record->subdivisions[0])) ? $record->subdivisions[0]->name : null;
    }

    public function ip2CityName(string $ipAddress): ?string
    {
        $this->readGeoLite2City();

        try {
            $record = $this->geoLiteCityReader->city($ipAddress);
        } catch (\GeoIp2\Exception\AddressNotFoundException $e) {

        }

        return (isset($record)) ? $record->city->name : null;
    }

    public function ip2ASN(string $ipAddress): array
    {
        $asn = [];
        $this->readGeoLite2ASN();

        try {
            $ip2asn         = $this->geoLiteASNReader->asn($ipAddress);
            $asn['name']    = $ip2asn->autonomousSystemOrganization; // Digi Romania S.A.
            $asn['number']  = $ip2asn->autonomousSystemNumber;       // 8708
            $asn['network'] = $ip2asn->network;                      // 2a02:2f08::/33
        } catch (\GeoIp2\Exception\AddressNotFoundException $e) {

        }

        return $asn;
    }

    /**
     * Return http:// or https://
     *
     * @return string
     */
    public function protocol(): string
    {
        // Reverse proxy
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            return $_SERVER['HTTP_X_FORWARDED_PROTO'] . '://';
        } else {
            return !empty($_SERVER['HTTPS']) ? "https://" : "http://";
        }
    }

    /**
     * Check is we have an SSL connection
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
     */
    public function ip(Request $request): string
    {
        // CloudFlare: The real visitor IP addresses
        // https://support.cloudflare.com/hc/en-us/articles/200170986-How-does-Cloudflare-handle-HTTP-Request-headers-
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        if (
            isset($_SERVER['HTTP_X_FORWARDED_FOR'])
            && false === strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',')
            && $this->ipIsValide($_SERVER['HTTP_X_FORWARDED_FOR'])
        ) {
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
     */
    public function ipIsValide(mixed $ip): bool
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
     * ----------------------------------------------------------------------------------
     * !! WARNING !! - Because of the reverse DNS lookups, this method is/might be slow
     *  --------------------------------------------------------------------------------
     *
     * Check if an IPv4 is a Google Bot (by hostname)
     */
    public function ipIsGoogleBot(string $IP): bool
    {
        if ($IP instanceof Request) {
            trigger_error('Method ' . __METHOD__ . ' with Request as parameter is deprecated. Use client Address IP (string) instead.', E_USER_DEPRECATED);
            $IP = $this->ip($IP);
        }

        /**
         * @TODO: instead of gethostbyaddr, use (https://developers.google.com/search/docs/crawling-indexing/verifying-googlebot):
         *      https://developers.google.com/static/search/apis/ipranges/googlebot.json
         *      https://developers.google.com/static/search/apis/ipranges/special-crawlers.json
         *      https://developers.google.com/static/search/apis/ipranges/user-triggered-fetchers.json
         *      https://developers.google.com/static/search/apis/ipranges/user-triggered-fetchers-google.json
         */

        $hostname = gethostbyaddr(trim($IP));

        $AuroraMatch = new AuroraMatch();

        return $AuroraMatch->matchAtLeastOneDomain($hostname, ['google.com', 'googlebot.com']);
    }

    /**
     * ----------------------------------------------------------------------------------
     * !! WARNING !! - Because of the reverse DNS lookups, this method is/might be slow
     *  --------------------------------------------------------------------------------
     *
     * Check if an IPv4 is a Microsoft/Bing bot (by hostname)
     */
    public function ipIsBingBot(string $IP): bool
    {
        if ($IP instanceof Request) {
            trigger_error('Method ' . __METHOD__ . ' with Request as parameter is deprecated. Use client Address IP (string) instead.', E_USER_DEPRECATED);
            $IP = $this->ip($IP);
        }

        /**
         * @TODO: instead of gethostbyaddr, use (https://www.bing.com/webmasters/help/how-to-verify-bingbot-3905dc26):
         *      https://www.bing.com/toolbox/bingbot.json
         */

        $hostname = gethostbyaddr(trim($IP));

        /** @var AuroraMatch $AuroraMatch */
        $AuroraMatch = new AuroraMatch();

        return $AuroraMatch->matchAtLeastOneDomain($hostname, ['msn.com', 'bing.com']);
    }

    /**
     * ----------------------------------------------------------------------------------
     * !! WARNING !! - Because of the reverse DNS lookups, this method is/might be slow
     *  --------------------------------------------------------------------------------
     *
     * Check if an IPv4 is a Google Bot or a Bing Bot (by hostname)
     */
    public function ipIsGoogleOrBingBot(string $IP): bool
    {
        return $this->ipIsGoogleBot($IP) || $this->ipIsBingBot($IP);
    }
}
