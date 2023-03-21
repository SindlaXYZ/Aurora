<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAnnotation;

// Core
use DateTimeImmutable;
use DateTimeInterface;

// Doctrine
use Doctrine\ORM\Mapping as ORM;

/**
 * Trait TemporalCrondTrait
 *  + `crondAt`
 */
trait TemporalCrondTrait
{
    /**
     * @var DateTimeInterface|null
     *
     * @ORM\Column(type="datetime_immutable", name="crond_at", nullable=true)
     */
    protected ?DateTimeInterface $crondAt = null;

    /**
     * @param DateTimeInterface|null $createdAt
     * @return $this
     */
    public function setCrondAt(?DateTimeInterface $crondAt): self
    {
        $this->crondAt = $crondAt;
        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getCrondAt(): ?DateTimeImmutable
    {
        return $this->crondAt;
    }

    /**
     * @return int
     */
    public function getCrondAtDiffSeconds(): int
    {
        return ($this->crondAt ? (time() - $this->crondAt->getTimestamp()) : 0);
    }
}
