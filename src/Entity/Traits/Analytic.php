<?php

namespace Sindla\Bundle\BorealisBundle\Entity\Traits;

// Doctrine
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Doctrine\ORM\PersistentCollection;

// Borealis
use Sindla\Bundle\BorealisBundle\Entity\Traits\Identifiable;
use Sindla\Bundle\BorealisBundle\Entity\Traits\TemporalCreated;

trait Analytic
{
    use Identifiable;
    use TemporalCreated;

    /*
    const TYPE_VIEW   = 1;
    const TYPE_SEARCH = 2;
    ...
    */

    /**
     * @var int
     *
     * @ORM\Column(name="type", type="smallint", nullable=false)
     */
    private $type;

    /**
     * @var string
     *
     * @ORM\Column(name="search", type="string", length=255, nullable=true)
     */
    private $search;

    /**
     * @var string
     *
     * @ORM\Column(name="ip", type="string", length=15, nullable=false)
     */
    private $ip;

    /**
     * ISO Alpha-2
     *
     * @var string
     *
     * @ORM\Column(name="country_code", type="string", length=2, nullable=true)
     */
    private $countryCode;

    /**
     * @var string
     *
     * @ORM\Column(name="user_agent", type="string", length=255, nullable=true)
     */
    private $userAgent;

    /**
     * @var bool
     *
     * @ORM\Column(name="is_boot", type="boolean", nullable=false, options={"default" : false})
     */
    private $isBot = false;

    /**
     * @var string
     *
     * @ORM\Column(name="os_name", type="string", length=255, nullable=true)
     */
    private $osName;

    /**
     * @var string
     *
     * @ORM\Column(name="os_version", type="string", length=255, nullable=true)
     */
    private $osVersion;

    /**
     * @var string
     *
     * @ORM\Column(name="os_architecture", type="string", length=255, nullable=true)
     */
    private $osArchitecture;

    /**
     * @var string
     *
     * @ORM\Column(name="user_uuid", type="string", length=255, nullable=true)
     */
    private $userUuid;

    /**
     * @var bool
     *
     * @ORM\Column(name="is_unique", type="boolean", nullable=false, options={"default" : false})
     */
    private $isUnique = false;

    /**
     * @var string
     *
     * @ORM\Column(name="referer", type="string", length=255, nullable=true)
     */
    private $referer;

    /**
     * @var string
     *
     * @ORM\Column(name="request_uri", type="string", length=255, nullable=false)
     */
    private $requestUri;

    /**
     * @var string
     *
     * @ORM\Column(name="request_hash", type="string", length=255, nullable=false)
     */
    private $requestHash;

    /**
     * @var array
     *
     * @ORM\Column(name="preferred_languages", type="json", nullable=true)
     */
    private $preferredLanguages;

    /**
     * @var array
     *
     * @ORM\Column(name="meta", type="json", nullable=true)
     */
    private $meta;

    /**
     * @var string
     *
     * @ORM\Column(name="reference_table", type="string", length=255, nullable=true)
     */
    private $referenceTable;

    /**
     * @var int
     *
     * @ORM\Column(name="reference_id", type="integer", nullable=true)
     */
    private $referenceId;

    /**
     * @return int
     */
    public function getType(): ?int
    {
        return $this->type;
    }

    /**
     * @param int $type
     * @return Analytic
     */
    public function setType(int $type): Analytic
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getSearch(): ?string
    {
        return $this->search;
    }

    /**
     * @param string $search
     * @return Analytic
     */
    public function setSearch(string $search): Analytic
    {
        $this->search = $search;
        return $this;
    }

    /**
     * @return string
     */
    public function getIp(): ?string
    {
        return $this->ip;
    }

    /**
     * @param string $ip
     * @return Analytic
     */
    public function setIp(string $ip): Analytic
    {
        $this->ip = $ip;
        return $this;
    }

    /**
     * @return string
     */
    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    /**
     * @param string $countryCode
     * @return Analytic
     */
    public function setCountryCode(?string $countryCode): Analytic
    {
        $this->countryCode = $countryCode;
        return $this;
    }

    /**
     * @return string
     */
    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    /**
     * @param string $userAgent
     * @return Analytic
     */
    public function setUserAgent(?string $userAgent): Analytic
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * @return bool
     */
    public function getIsBot(): ?bool
    {
        return $this->isBot;
    }

    /**
     * @param bool $isBot
     * @return Analytic
     */
    public function setIsBot(bool $isBot): Analytic
    {
        $this->isBot = $isBot;
        return $this;
    }

    /**
     * @return string
     */
    public function getOsName(): ?string
    {
        return $this->osName;
    }

    /**
     * @param string $osName
     * @return Analytic
     */
    public function setOsName(?string $osName): Analytic
    {
        $this->osName = $osName;
        return $this;
    }

    /**
     * @return string
     */
    public function getOsVersion(): ?string
    {
        return $this->osVersion;
    }

    /**
     * @param string $osVersion
     * @return Analytic
     */
    public function setOsVersion(?string $osVersion): Analytic
    {
        $this->osVersion = $osVersion;
        return $this;
    }

    /**
     * @return string
     */
    public function getOsArchitecture(): ?string
    {
        return $this->osArchitecture;
    }

    /**
     * @param string $osArchitecture
     * @return Analytic
     */
    public function setOsArchitecture(?string $osArchitecture): Analytic
    {
        $this->osArchitecture = $osArchitecture;
        return $this;
    }

    /**
     * @return string
     */
    public function getUserUuid(): ?string
    {
        return $this->userUuid;
    }

    /**
     * @param string $userUuid
     * @return Analytic
     */
    public function setUserUuid(?string $userUuid): Analytic
    {
        $this->userUuid = $userUuid;
        return $this;
    }

    /**
     * @return bool
     */
    public function getIsUnique(): ?bool
    {
        return $this->isUnique;
    }

    /**
     * @param bool $isUnique
     * @return Analytic
     */
    public function setIsUnique(bool $isUnique): Analytic
    {
        $this->isUnique = $isUnique;
        return $this;
    }

    /**
     * @return string
     */
    public function getReferer(): ?string
    {
        return $this->referer;
    }

    /**
     * @param string $referer
     * @return Analytic
     */
    public function setReferer(?string $referer): Analytic
    {
        $this->referer = $referer;
        return $this;
    }

    /**
     * @return string
     */
    public function getRequestUri(): ?string
    {
        return $this->requestUri;
    }

    /**
     * @param string $requestUri
     * @return Analytic
     */
    public function setRequestUri(string $requestUri): Analytic
    {
        $this->requestUri = $requestUri;
        return $this;
    }

    /**
     * @return string
     */
    public function getRequestHash(): ?string
    {
        return $this->requestHash;
    }

    /**
     * @param string $requestHash
     * @return Analytic
     */
    public function setRequestHash(string $requestHash): Analytic
    {
        $this->requestHash = $requestHash;
        return $this;
    }

    /**
     * @return array
     */
    public function getPreferredLanguages(): ?array
    {
        return $this->preferredLanguages;
    }

    /**
     * @param array $preferredLanguages
     * @return Analytic
     */
    public function setPreferredLanguages(?array $preferredLanguages): Analytic
    {
        $this->preferredLanguages = $preferredLanguages;
        return $this;
    }

    /**
     * @return array
     */
    public function getMeta(): ?array
    {
        return $this->meta;
    }

    /**
     * @param array $meta
     * @return Analytic
     */
    public function setMeta(?array $meta): Analytic
    {
        $this->meta = $meta;
        return $this;
    }

    /**
     * @return string
     */
    public function getReferenceTable(): ?string
    {
        return $this->referenceTable;
    }

    /**
     * @param string $referenceTable
     * @return Analytic
     */
    public function setReferenceTable(?string $referenceTable): Analytic
    {
        $this->referenceTable = $referenceTable;
        return $this;
    }

    /**
     * @return int
     */
    public function getReferenceId(): ?int
    {
        return $this->referenceId;
    }

    /**
     * @param int $referenceId
     * @return Analytic
     */
    public function setReferenceId(?int $referenceId): Analytic
    {
        $this->referenceId = $referenceId;
        return $this;
    }
}