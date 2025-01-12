<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraIP;

/**
 * https://iplocation.io/ip/
 * https://db-ip.com/api/basic/
 * https://ipinfo.io/
 */
class AuroraIP
{
    private string $ip;

    public function ip(string $ip): self
    {
        $this->ip = $ip;
        return $this;
    }

    public function isIPV4(): bool
    {
        return (bool)filter_var($this->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    public function isPublicIPV4(): bool
    {
        return (bool)filter_var($this->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    public function isPrivateIPV4(): bool
    {
        return !$this->isPublicIPV4();
    }

    public function isIPV6(): bool
    {
        return (bool)filter_var($this->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    public function isPublicIPV6(): bool
    {
        return (bool)filter_var($this->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    private function isPrivateIPV6(): bool
    {
        return !$this->isPublicIPV6();
    }

    public function isPrivate(): bool
    {
        return $this->isPrivateIPV4() || $this->isPrivateIPV6();
    }

    public function isPublic(): bool
    {
        return $this->isPublicIPV4() || $this->isPublicIPV6();
    }

    public function isGoogle(): bool
    {
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
