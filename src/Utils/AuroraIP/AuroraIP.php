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
