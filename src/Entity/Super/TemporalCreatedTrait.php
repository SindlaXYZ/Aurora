<?php

namespace Sindla\Bundle\AuroraBundle\Entity\Super;

// Core
use DateTimeInterface;
use DateTimeImmutable;

// Doctrine
use Doctrine\ORM\Mapping as ORM;

/**
 * Trait TemporalCreatedTrait
 *  + `createdAt`
 */
trait TemporalCreatedTrait
{
    /**
     * @var DateTimeInterface|null
     *
     * @ORM\Column(type="datetime_immutable", name="created_at", nullable=true)
     */
    protected ?DateTimeInterface $createdAt = null;

    /** @ORM\PrePersist */
    public function prePersistHook()
    {
        $this->setCreatedAt(new DateTimeImmutable());

        if (method_exists($this, 'setUpdatedAt')) {
            $this->setUpdatedAt(new DateTimeImmutable());
        }
    }

    /**
     * @param DateTimeInterface|null $createdAt
     * @return $this
     */
    public function setCreatedAt(?DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return int
     */
    public function getCreatedAtDiffSeconds(): int
    {
        return ($this->createdAt ? (time() - $this->createdAt->getTimestamp()) : 0);
    }
}
