<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraIP;

/**
 * https://iplocation.io/ip/
 * https://db-ip.com/api/basic/
 * https://ipinfo.io/
 */
class AuroraIP
{
    use Google;

    public function isIPV4(string $ip): bool
    {
        return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    public function isPublicIPV4(string $ip): bool
    {
        return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    public function isPrivateIPV4(string $ip): bool
    {
        return !$this->isPublicIPV4($ip);
    }

    public function isIPV6(string $ip): bool
    {
        return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    public function isIPInSubnet(string $ipv6, string $cidr): bool
    {
        [$subnet, $prefixLength] = explode('/', $cidr);
        $prefixLength = (int)$prefixLength;

        $ipBin     = inet_pton($ipv6);
        $subnetBin = inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        // 32 for IPv4, 128 for IPv6
        $totalBits = strlen($ipBin) * 8;

        $mask = str_repeat("\xff", (int)($prefixLength / 8));
        $rest = $prefixLength % 8;
        if ($rest > 0) {
            $mask .= chr(0xff << (8 - $rest) & 0xff);
        }
        $mask         = str_pad($mask, strlen($ipBin), "\0");
        $ipMasked     = $ipBin & $mask;
        $subnetMasked = $subnetBin & $mask;

        return ($ipMasked === $subnetMasked);
    }

    public function isPublicIPV6(string $ip): bool
    {
        return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    private function isPrivateIPV6(string $ip): bool
    {
        return !$this->isPublicIPV6($ip);
    }

    public function isPrivate(string $ip): bool
    {
        return $this->isPrivateIPV4($ip) || $this->isPrivateIPV6($ip);
    }

    public function isPublic(string $ip): bool
    {
        return $this->isPublicIPV4($ip) || $this->isPublicIPV6($ip);
    }

    public function isGoogle(string $ip): bool
    {
        if ($this->isIPV4($ip) && $this->isIPV6($ip)) {
            return false;
        }

        // https://developers.google.com/static/search/apis/ipranges/googlebot.json
        foreach ($this->googleBotAndCrawlerIPS as $googleBotIP => $googleBotIPVersion) {
            if ($this->isIPInSubnet($ip, $googleBotIP)) {
                return true;
            }
        }

        /**
         * @TODO: instead of gethostbyaddr, use (https://developers.google.com/search/docs/crawling-indexing/verifying-googlebot):
         *      https://developers.google.com/static/search/apis/ipranges/googlebot.json
         *      https://developers.google.com/static/search/apis/ipranges/special-crawlers.json
         *      https://developers.google.com/static/search/apis/ipranges/user-triggered-fetchers.json
         *      https://developers.google.com/static/search/apis/ipranges/user-triggered-fetchers-google.json
         */

        return false;
    }

    public function isBing(): bool
    {
        /**
         * @TODO: instead of gethostbyaddr, use (https://www.bing.com/webmasters/help/how-to-verify-bingbot-3905dc26):
         *      https://www.bing.com/toolbox/bingbot.json
         */

        return false;
    }

    public function getCountryCode(): ?string
    {
        // @TODO: integrate with curl https://ipinfo.io/$this->ip/json?token=$_ENV['IPINFOIO_TOKEN']
        return null;
    }
}
