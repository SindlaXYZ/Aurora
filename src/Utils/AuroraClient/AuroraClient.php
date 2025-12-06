<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraClient;

use GeoIp2\Database\Reader;
use Symfony\Component\DependencyInjection\Container;

class AuroraClient
{
    private const array DOCUMENTATION_CIDRS
        = [
            '192.0.2.0/24',    // TEST-NET-1
            '198.51.100.0/24', // TEST-NET-2
            '203.0.113.0/24',  // TEST-NET-3
            '2001:db8::/32',   // IPv6 documentation prefix
        ];
    private ?Reader $geoLiteCountryReader = null;
    private ?Reader $geoLiteCityReader    = null;
    private ?Reader $geoLiteASNReader     = null;

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

        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
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
     * Check if an IP is valid
     */
    public function ipIsValid(mixed $ip): bool
    {
        if (!is_string($ip) && !is_numeric($ip)) {
            return false;
        }

        $ipString = trim((string)$ip);

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

        $subnet       = trim($subnet);
        $prefixLength = trim($prefixLength);

        if ($subnet === '' || $prefixLength === '' || !ctype_digit($prefixLength)) {
            return false;
        }

        $prefixLength = (int)$prefixLength;
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

        if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return $prefLanguages;
        }

        $languages           = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $normalizedLanguages = [];

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
                    $quality = (float)$value;
                    break;
                }
            }

            if ($quality <= 0) {
                continue;
            }

            if ($quality > 1) {
                $quality = 1.0;
            }

            $normalizedLocale = $this->normalizeLocale($locale);
            $mapKey           = strtolower($normalizedLocale);

            if (
                !isset($normalizedLanguages[$mapKey])
                || $quality > $normalizedLanguages[$mapKey]['quality']
            ) {
                $normalizedLanguages[$mapKey] = [
                    'locale'  => $normalizedLocale,
                    'quality' => $quality,
                ];
            }
        }

        foreach ($normalizedLanguages as $data) {
            $prefLanguages[$data['locale']] = $data['quality'];
        }

        if ($prefLanguages !== []) {
            arsort($prefLanguages, SORT_NUMERIC);
        }

        return $prefLanguages;
    }

    private function normalizeLocale(string $locale): string
    {
        $normalized = str_replace('_', '-', $locale);
        $parts      = explode('-', $normalized);

        foreach ($parts as $index => $part) {
            if ($part === '') {
                continue;
            }

            if ($index === 0) {
                $parts[$index] = strtolower($part);
                continue;
            }

            $length = strlen($part);

            if ($length === 2) {
                $parts[$index] = strtoupper($part);
            } else if ($length === 4) {
                $parts[$index] = ucfirst(strtolower($part));
            } else {
                $parts[$index] = strtolower($part);
            }
        }

        return implode('-', $parts);
    }
}
