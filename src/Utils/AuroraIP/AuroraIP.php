<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraIP;

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
        return false;
    }

    public function isBing(): bool
    {
        return false;
    }

    public function getCountryCode(): ?string
    {
        // @TODO: integrate with curl https://ipinfo.io/$this->ip/json?token=$_ENV['IPINFOIO_TOKEN']
        return null;
    }
}
