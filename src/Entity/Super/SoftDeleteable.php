<?php

namespace Sindla\Bundle\AuroraBundle\Entity\Super;

// Core
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Trait TemporalCreatedTrait
 *  + `deletedAt`
 */
trait SoftDeleteable
{
    /**
     * @var DateTimeInterface|null
     *
     * @ORM\Column(type="datetime_immutable", name="deleted_at", nullable=true)
     */
    protected ?DateTimeInterface $deletedAt = null;

    /**
     * @param DateTimeInterface|null $deletedAt
     * @return $this
     */
    public function setDeletedAt(?DateTimeInterface $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /**
     * Check if the entity has been soft deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }
}
