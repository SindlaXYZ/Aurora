<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait Created
{
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeInterface $createdAt = null;

    #[ORM\PrePersist]
    public function prePersistHook(): void
    {
        if (!isset($this->createdAt)) {
            $this->setCreatedAt(new DateTimeImmutable());
        }

        if (method_exists($this, 'setUpdatedAt') && !isset($this->updatedAt)) {
            $this->setUpdatedAt(new DateTimeImmutable());
        }
    }

    /**
     * @return DateTimeInterface|null
     */
    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * @param DateTimeInterface|null $createdAt
     *
     * @return $this
     */
    public function setCreatedAt(?DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    // ----------------------------------------------------------------------------------------------------------------------------------------------
    // -- CUSTOM METHODS ----------------------------------------------------------------------------------------------------------------------------

    /**
     * @return int
     */
    public function getCreatedAtLifespanAsSeconds(): int
    {
        return $this->createdAt ? (new \DateTime())->getTimestamp() - $this->createdAt->getTimestamp() : 0;
    }

    /**
     * @return int
     */
    public function getCreatedAtLifespanAsMinutes(): int
    {
        return round($this->getCreatedAtLifespanAsSeconds() / 60);
    }

    /**
     * @return int
     */
    public function getCreatedAtLifespanAsHours(): int
    {
        return round($this->getCreatedAtLifespanAsMinutes() / 60);
    }

    /**
     * @return int
     */
    public function getCreatedAtLifespanAsDays(): int
    {
        return round($this->getCreatedAtLifespanAsHours() / 24);
    }
}
