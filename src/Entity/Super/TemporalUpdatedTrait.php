<?php

namespace Sindla\Bundle\AuroraBundle\Entity\Super;

// Core
use DateTimeInterface;

// Doctrine
use Doctrine\ORM\Mapping as ORM;

/**
 * Trait TemporalUpdatedTrait
 * + `updatedAt`
 */
trait TemporalUpdatedTrait
{
    /**
     * @var DateTimeInterface|null
     *
     * @ORM\Column(type="datetime", name="updated_at", nullable=true)
     */
    protected ?DateTimeInterface $updatedAt = null;

    /** @ORM\PreUpdate */
    public function preUpdateHook()
    {
        $this->setUpdatedAt(new DateTimeImmutable());
    }

    /**
     * @param DateTimeInterface|null $createdAt
     * @return $this
     */
    public function setUpdatedAt(?DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return int
     */
    public function getUpdatedAtDiffSeconds(): int
    {
        return ($this->updatedAt ? (time() - $this->updatedAt->getTimestamp()) : 0);
    }
}
