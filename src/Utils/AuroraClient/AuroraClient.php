<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraClient;

use GeoIp2\Database\Reader;
use Sindla\Bundle\AuroraBundle\Utils\AuroraIP\AuroraIP;
use Sindla\Bundle\AuroraBundle\Utils\AuroraMatch\AuroraMatch;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

class AuroraClient
{
    private const DOCUMENTATION_CIDRS = [
        '192.0.2.0/24',    // TEST-NET-1
        '198.51.100.0/24', // TEST-NET-2
        '203.0.113.0/24',  // TEST-NET-3
        '2001:db8::/32',   // IPv6 documentation prefix
    ];
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
            $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'];

            if (is_string($forwardedProto)) {
                $candidates = explode(',', $forwardedProto);

                foreach ($candidates as $candidate) {
                    $candidate = trim($candidate);

                    if ($candidate === '') {
                        continue;
                    }

                    $normalized = strtolower($candidate);

                    if (str_contains($normalized, '://')) {
                        [$normalized] = explode('://', $normalized, 2);
                    }

                    if ($normalized === 'https') {
                        return 'https://';
                    }

                    if ($normalized === 'http') {
                        return 'http://';
                    }

                    return $normalized . '://';
                }
            }
        }

        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return 'https://';
        }

        return 'http://';
    }

    /**
     * Check is we have an SSL connection
     */
    public function isSSL(): bool
    {
        return preg_match('/https/i', $this->protocol()) === 1;
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

        $forwardedForHeader = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;

        if (is_string($forwardedForHeader)) {
            $forwardedForSingleIp = trim($forwardedForHeader);

            if (
                $forwardedForSingleIp !== ''
                && false === strpos($forwardedForHeader, ',')
                && $this->ipIsValid($forwardedForSingleIp)
            ) {
                return $forwardedForSingleIp;
            }
        }

        if (is_string($forwardedForHeader) && strpos($forwardedForHeader, ',') !== false) {
            foreach (explode(',', $forwardedForHeader) as $ip) {
                $ip = trim($ip);
                if ($this->ipIsValid($ip)) {
                    return $ip;
                }
            }
        }

        if ($this->ipIsValid($request->getClientIp())) {
            return $request->getClientIp();
        }

        if (isset($_SERVER['HTTP_CLIENT_IP']) && $this->ipIsValid($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }

        if (isset($_SERVER['REMOTE_ADDR']) && $this->ipIsValid($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return '127.0.0.1';
    }

    /**
     * Check if an IP is valid
     */
    public function ipIsValid(mixed $ip): bool
    {
        if (!is_string($ip) && !is_numeric($ip)) {
            return false;
        }

        $ipString = trim((string) $ip);

        if ($ipString === '') {
            return false;
        }

        if (filter_var($ipString, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if ($this->isDocumentationIp($ipString)) {
            return true;
        }

        $ipIsValid = filter_var(
            $ipString,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        return $ipIsValid !== false;
    }

    private function isDocumentationIp(string $ip): bool
    {
        foreach (self::DOCUMENTATION_CIDRS as $cidr) {
            if ($this->ipMatchesCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    private function ipMatchesCidr(string $ip, string $cidr): bool
    {
        if (strpos($cidr, '/') === false) {
            return false;
        }

        [$subnet, $prefixLength] = explode('/', $cidr, 2);

        if ($subnet === '') {
            return false;
        }

        $prefixLength = (int) trim($prefixLength);
        $ipBinary     = inet_pton($ip);
        $subnetBinary = inet_pton($subnet);

        if (
            $ipBinary === false
            || $subnetBinary === false
            || strlen($ipBinary) !== strlen($subnetBinary)
            || $prefixLength < 0
        ) {
            return false;
        }

        $totalBits = strlen($ipBinary) * 8;

        if ($prefixLength > $totalBits) {
            return false;
        }

        $maskBytes = intdiv($prefixLength, 8);
        $mask      = str_repeat("\xff", $maskBytes);
        $remainder = $prefixLength % 8;

        if ($remainder > 0) {
            $mask .= chr((0xff << (8 - $remainder)) & 0xff);
        }

        $mask = str_pad($mask, strlen($ipBinary), "\0");

        return ($ipBinary & $mask) === ($subnetBinary & $mask);
    }

    /**
     * Return the client browser preferred languages
     */
    public function preferredLanguages(): array
    {
        $prefLanguages = [];

        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $languages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);

            foreach ($languages as $language) {
                $language = trim($language);

                if ($language === '') {
                    continue;
                }

                $parts   = array_map('trim', explode(';', $language));
                $locale  = array_shift($parts);
                $quality = 1.0;

                if ($locale === '' || $locale === null) {
                    continue;
                }

                foreach ($parts as $part) {
                    if ($part === '') {
                        continue;
                    }

                    if (!str_contains($part, '=')) {
                        continue;
                    }

                    [$key, $value] = array_map('trim', explode('=', $part, 2));

                    if (strcasecmp($key, 'q') === 0 && is_numeric($value)) {
                        $quality = (float) $value;
                        break;
                    }
                }

                if (!isset($prefLanguages[$locale]) || $quality > $prefLanguages[$locale]) {
                    $prefLanguages[$locale] = $quality;
                }
            }

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
    public function ipIsGoogleBot(Request|string $IP): bool
    {
        if ($IP instanceof Request) {
            trigger_error('Method ' . __METHOD__ . ' with Request as parameter is deprecated. Use client Address IP (string) instead.', E_USER_DEPRECATED);
            $IP = $this->ip($IP);
        }

        $ipString = trim((string) $IP);

        if (!$this->ipIsValid($ipString)) {
            return false;
        }

        /**
         * @TODO: instead of gethostbyaddr, use (https://developers.google.com/search/docs/crawling-indexing/verifying-googlebot):
         *      https://developers.google.com/static/search/apis/ipranges/googlebot.json
         *      https://developers.google.com/static/search/apis/ipranges/special-crawlers.json
         *      https://developers.google.com/static/search/apis/ipranges/user-triggered-fetchers.json
         *      https://developers.google.com/static/search/apis/ipranges/user-triggered-fetchers-google.json
         */

        $hostname = gethostbyaddr($ipString);

        $AuroraMatch = new AuroraMatch();

        if (is_string($hostname) && $hostname !== '' && $hostname !== $ipString) {
            if ($AuroraMatch->matchAtLeastOneDomain($hostname, ['google.com', 'googlebot.com'])) {
                return true;
            }
        }

        $auroraIP = new AuroraIP();

        return $auroraIP->isGoogle($ipString);
    }

    /**
     * ----------------------------------------------------------------------------------
     * !! WARNING !! - Because of the reverse DNS lookups, this method is/might be slow
     *  --------------------------------------------------------------------------------
     *
     * Check if an IPv4 is a Microsoft/Bing bot (by hostname)
     */
    public function ipIsBingBot(Request|string $IP): bool
    {
        if ($IP instanceof Request) {
            trigger_error('Method ' . __METHOD__ . ' with Request as parameter is deprecated. Use client Address IP (string) instead.', E_USER_DEPRECATED);
            $IP = $this->ip($IP);
        }

        /**
         * @TODO: instead of gethostbyaddr, use (https://www.bing.com/webmasters/help/how-to-verify-bingbot-3905dc26):
         *      https://www.bing.com/toolbox/bingbot.json
         */

        $ipString = trim((string) $IP);

        if (!$this->ipIsValid($ipString)) {
            return false;
        }

        $hostname = gethostbyaddr($ipString);

        /** @var AuroraMatch $AuroraMatch */
        $AuroraMatch = new AuroraMatch();

        if (is_string($hostname) && $hostname !== '' && $hostname !== $ipString) {
            if ($AuroraMatch->matchAtLeastOneDomain($hostname, ['msn.com', 'bing.com'])) {
                return true;
            }
        }

        $auroraIP = new AuroraIP();

        return $auroraIP->isBing($ipString);
    }

    /**
     * ----------------------------------------------------------------------------------
     * !! WARNING !! - Because of the reverse DNS lookups, this method is/might be slow
     *  --------------------------------------------------------------------------------
     *
     * Check if an IPv4 is a Google Bot or a Bing Bot (by hostname)
     */
    public function ipIsGoogleOrBingBot(Request|string $IP): bool
    {
        return $this->ipIsGoogleBot($IP) || $this->ipIsBingBot($IP);
    }
}
