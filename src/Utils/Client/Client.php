<?php

namespace Sindla\Bundle\AuroraBundle\Utils\Client;

// Symfony
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

// GeoIp2
use GeoIp2\Database\Reader;

/**
 * Class Client
 *
 * @package AuroraBundle\Utils
 */
class Client
{
    private $container;

    public function __construct(Container $Container)
    {
        $this->container = $Container;
    }

    public function ip2CountryCode(string $ipAddress)
    {
        $GeoLite2CountryFile = $this->container->getParameter('aurora.resources') . '/maxmind-geoip2/GeoLite2Country.mmdb';
        if (!is_file($GeoLite2CountryFile)) {
            throw new \Exception("[{$GeoLite2CountryFile}] file not found!");
        }

        $reader = new Reader($GeoLite2CountryFile);
        try {
            $record = $reader->country($ipAddress);
        } catch (\GeoIp2\Exception\AddressNotFoundException $e) {

        }

        if (isset($record)) {
            return $record->country->isoCode;
        }

        return null;
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
    public function preferredLanguages()
    {
        $prefLanguages = [];

        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $prefLanguages = array_reduce(
                explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']),
                function ($res, $el) {
                    list($l, $q) = array_merge(explode(';q=', $el), [1]);
                    $res[$l] = (float)$q;
                    return $res;
                }, []);
            arsort($prefLanguages);
        }

        return $prefLanguages;
    }

    /**
     * Check if an IPv4 is a Google bot (by hostname)
     *
     * @param Request $request
     *
     * @return bool
     */
    public function ipIsGoogleBot(Request $request)
    {
        $hostname = gethostbyaddr(trim($this->ip($request)));

        preg_match('/^crawl-[0-9]+-[0-9]+-[0-9]+-[0-9]+\.googlebot\.com$/i', $hostname, $matches);

        if (is_array($matches) && count($matches) > 0 && isset($matches[0]) && !empty($matches[0])) {
            return true;
        } else {
            return false;
        }
    }
}