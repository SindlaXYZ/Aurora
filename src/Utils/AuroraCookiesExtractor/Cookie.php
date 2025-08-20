<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraCookiesExtractor;

class Cookie
{
    private string             $name;
    private string             $value;
    private string             $domain;
    private string             $path;
    private \DateTimeImmutable $expires;
    private int                $size;
    private bool               $httpOnly;
    private bool               $secure;
    private string             $sameSite;
    private string             $priority;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): self
    {
        $this->domain = $domain;
        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function getExpires(): \DateTimeImmutable
    {
        return $this->expires;
    }

    public function setExpires(\DateTimeImmutable $expires): self
    {
        $this->expires = $expires;
        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    public function isHttpOnly(): bool
    {
        return $this->httpOnly;
    }

    public function setHttpOnly(bool $httpOnly): self
    {
        $this->httpOnly = $httpOnly;
        return $this;
    }

    public function isSecure(): bool
    {
        return $this->secure;
    }

    public function setSecure(bool $secure): self
    {
        $this->secure = $secure;
        return $this;
    }

    public function getSameSite(): string
    {
        return $this->sameSite;
    }

    public function setSameSite(string $sameSite): self
    {
        $this->sameSite = $sameSite;
        return $this;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): self
    {
        $this->priority = $priority;
        return $this;
    }
}
