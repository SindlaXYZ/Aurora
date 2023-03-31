<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait TimestampableUpdated
{
    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeInterface $updatedAt = null;

    #[ORM\PreUpdate]
    public function preUpdateHook(): void
    {
        $this->setUpdatedAt(new DateTimeImmutable());
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // ----------------------------------------------------------------------------------------------------------------------------------------------
    // -- CUSTOM METHODS ----------------------------------------------------------------------------------------------------------------------------

    /**
     * @return int
     */
    public function getUpdatedAtLifespanAsSeconds(): int
    {
        return $this->updatedAt ? (new \DateTime())->getTimestamp() - $this->updatedAt->getTimestamp() : 0;
    }

    /**
     * @return int
     */
    public function getUpdatedAtLifespanAsMinutes(): int
    {
        return round($this->getUpdatedAtLifespanAsSeconds() / 60);
    }

    /**
     * @return int
     */
    public function getUpdatedAtLifespanAsHours(): int
    {
        return round($this->getUpdatedAtLifespanAsMinutes() / 60);
    }

    /**
     * @return int
     */
    public function getUpdatedAtLifespanAsDays(): int
    {
        return round($this->getUpdatedAtLifespanAsHours() / 24);
    }
}
