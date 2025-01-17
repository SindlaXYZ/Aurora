<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraIP;

/**
 * https://iplocation.io/ip/
 * https://db-ip.com/api/basic/
 * https://ipinfo.io/
 */
class AuroraIP
{
    use KnownBotsAndCrawlers;

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
        foreach ($this->googleBotAndCrawlerIPS as $botIP => $botIPVersion) {
            if ($this->isIPInSubnet($ip, $botIP)) {
                return true;
            }
        }

        return false;
    }

    public function isBing(string $ip): bool
    {
        if ($this->isIPV4($ip) && $this->isIPV6($ip)) {
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
        if ($this->isIPV4($ip) && $this->isIPV6($ip)) {
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
        if ($this->isIPV4($ip) && $this->isIPV6($ip)) {
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

    public function getCountryCode(): ?string
    {
        // @TODO: integrate with curl https://ipinfo.io/$this->ip/json?token=$_ENV['IPINFOIO_TOKEN']
        return null;
    }
}
