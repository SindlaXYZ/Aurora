<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraIP;

use Symfony\Component\HttpFoundation\Request;

/**
 * https://iplocation.io/ip/
 * https://db-ip.com/api/basic/
 * https://ipinfo.io/
 */
class AuroraIP
{
    use KnownBotsAndCrawlers;

    /**
     * Cloudflare edge servers: https://www.cloudflare.com/ips/
     */
    public const array CLOUDFLARE_IPS
        = [
            '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '104.16.0.0/13',
            '104.24.0.0/14',
            '172.64.0.0/13',
            '131.0.72.0/22',
            '2400:cb00::/32',
            '2606:4700::/32',
            '2803:f800::/32',
            '2405:b500::/32',
            '2405:8100::/32',
            '2a06:98c0::/29',
            '2c0f:f248::/32',
        ];

    /**
     * Returns the client IP
     *
     * The CF-Connecting-IP, X-Forwarded-For and Client-IP headers are sent by the client, so they must not be trusted blindly:
     * anyone could otherwise choose the IP that is logged, geolocated and reported to the BlackHole API.
     * X-Forwarded-For is honoured only from the trusted proxies (framework.trusted_proxies) by Request::getClientIp(), and
     * CF-Connecting-IP only when the request comes from a Cloudflare edge server.
     */
    public function ip(Request $request): string
    {
        // null when REMOTE_ADDR is not set (e.g. CLI, workers, sub-requests created with "new Request()")
        $clientIp = $request->getClientIp();

        if (null !== $clientIp && $this->ipIsValid($clientIp)) {
            // CloudFlare: The real visitor IP addresses
            // https://developers.cloudflare.com/fundamentals/reference/http-headers/#cf-connecting-ip
            $cfConnectingIp = trim((string)$request->headers->get('CF-Connecting-IP'));

            if ($this->isCloudflare($clientIp) && $this->ipIsValid($cfConnectingIp)) {
                return $cfConnectingIp;
            }

            return $clientIp;
        }

        if (isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR']) && $this->ipIsValid($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return '127.0.0.1';
    }

    /**
     * Returns true when the IP belongs to a Cloudflare edge server
     */
    public function isCloudflare(string $ip): bool
    {
        if (!$this->ipIsValid($ip)) {
            return false;
        }

        // An IPv4 address seen by a dual-stack server: "::ffff:162.158.1.1"
        $binary = inet_pton($ip);
        if (false !== $binary && str_starts_with($binary, str_repeat("\0", 10) . "\xff\xff")) {
            $ip = (string)inet_ntop(substr($binary, 12));
        }

        foreach (self::CLOUDFLARE_IPS as $cidr) {
            if ($this->isIPInSubnet($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    public function ipIsValid(string $ip): bool
    {
        return $this->isIPV4($ip) || $this->isIPV6($ip);
    }

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
        return $this->isIPV4($ip) && !$this->isPublicIPV4($ip);
    }

    public function isIPV6(string $ip): bool
    {
        return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    public function isIPInSubnet(string $ip, string $cidr): bool
    {
        if (strpos($cidr, '/') === false) {
            return false;
        }

        [$subnet, $prefixLength] = array_map('trim', explode('/', $cidr, 2));

        if ($subnet === '' || $prefixLength === '' || !ctype_digit($prefixLength)) {
            return false;
        }

        $prefixLength = (int)$prefixLength;

        $ipBin     = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        // 32 for IPv4, 128 for IPv6
        $totalBits = strlen($ipBin) * 8;

        if ($prefixLength < 0 || $prefixLength > $totalBits) {
            return false;
        }

        $mask = str_repeat("\xff", (int)($prefixLength / 8));
        $rest = $prefixLength % 8;
        if ($rest > 0) {
            $mask .= chr((0xff << (8 - $rest)) & 0xff);
        }
        $mask         = str_pad($mask, strlen($ipBin), "\0");
        $ipMasked     = $ipBin & $mask;
        $subnetMasked = $subnetBin & $mask;

        return $ipMasked === $subnetMasked;
    }

    public function isPublicIPV6(string $ip): bool
    {
        return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    private function isPrivateIPV6(string $ip): bool
    {
        return $this->isIPV6($ip) && !$this->isPublicIPV6($ip);
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
        if (!$this->isIPV4($ip) && !$this->isIPV6($ip)) {
            return false;
        }

        // https://developers.google.com/static/search/apis/ipranges/googlebot.json
        foreach ($this->googleBotAndCrawlerIPS as $botIP => $botIPVersion) {
            if ($this->isIPInSubnet($ip, $botIP)) {
                return true;
            }
        }

        return false;
    }

    public function isBing(string $ip): bool
    {
        if (!$this->isIPV4($ip) && !$this->isIPV6($ip)) {
            return false;
        }

        // https://www.bing.com/webmasters/help/how-to-verify-bingbot-3905dc26
        foreach ($this->bingBotAndCrawlerIPS as $botIP => $botIPVersion) {
            if ($this->isIPInSubnet($ip, $botIP)) {
                return true;
            }
        }

        return false;
    }

    public function isApple(string $ip): bool
    {
        if (!$this->isIPV4($ip) && !$this->isIPV6($ip)) {
            return false;
        }

        // https://support.apple.com/en-us/119829
        foreach ($this->appleBotAndCrawlerIPS as $botIP => $botIPVersion) {
            if ($this->isIPInSubnet($ip, $botIP)) {
                return true;
            }
        }

        return false;
    }

    public function isOpenAI(string $ip): bool
    {
        if (!$this->isIPV4($ip) && !$this->isIPV6($ip)) {
            return false;
        }

        // https://platform.openai.com/docs/bots/overview-of-openai-crawlers
        foreach ($this->openAIBotAndCrawlerIPS as $botIP => $botIPVersion) {
            if ($this->isIPInSubnet($ip, $botIP)) {
                return true;
            }
        }

        return false;
    }

    public function isUpTimeRobot(string $ip): bool
    {
        if (!$this->isIPV4($ip) && !$this->isIPV6($ip)) {
            return false;
        }

        // https://uptimerobot.com/help/locations/
        foreach ($this->uptimeRobotIPS as $botIP => $botIPVersion) {
            if ($this->isIPInSubnet($ip, $botIP)) {
                return true;
            }
        }

        return false;
    }

    public function isBot(string $ip): bool
    {
        return $this->isGoogle($ip)
            || $this->isBing($ip)
            || $this->isApple($ip)
            || $this->isOpenAI($ip)
            || $this->isUpTimeRobot($ip);
    }

    public function getCountryCode(): ?string
    {
        // @TODO: integrate with curl https://ipinfo.io/$this->ip/json?token=$_ENV['IPINFOIO_TOKEN']
        return null;
    }
}
