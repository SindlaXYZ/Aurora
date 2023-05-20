<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait TimestampableCroned
{
    #[ORM\Column(name: 'croned_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeInterface $cronedAt = null;
    
    /**
     * @return DateTimeInterface|null
     */
    public function getCronedAt(): ?DateTimeInterface
    {
        return $this->cronedAt;
    }

    /**
     * @param DateTimeInterface|null $createdAt
     *
     * @return $this
     */
    public function setCronedAt(?DateTimeInterface $createdAt): self
    {
        $this->cronedAt = $createdAt;
        return $this;
    }

    // ----------------------------------------------------------------------------------------------------------------------------------------------
    // -- CUSTOM METHODS ----------------------------------------------------------------------------------------------------------------------------

    /**
     * @return int
     */
    public function getCronedAtLifespanAsSeconds(): int
    {
        return $this->cronedAt ? (new \DateTime())->getTimestamp() - $this->cronedAt->getTimestamp() : 0;
    }

    /**
     * @return int
     */
    public function getCronedAtLifespanAsMinutes(): int
    {
        return round($this->getCronedAtLifespanAsSeconds() / 60);
    }

    /**
     * @return int
     */
    public function getCronedAtLifespanAsHours(): int
    {
        return round($this->getCronedAtLifespanAsMinutes() / 60);
    }

    /**
     * @return int
     */
    public function getCronedAtLifespanAsDays(): int
    {
        return round($this->getCronedAtLifespanAsHours() / 24);
    }
}
