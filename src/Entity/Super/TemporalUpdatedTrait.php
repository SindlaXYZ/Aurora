<?php

namespace Sindla\Bundle\AuroraBundle\Entity\Super;

// Doctrine
use Doctrine\ORM\Mapping as ORM;

/**
 * Trait TemporalUpdatedTrait
 * + `updatedAt`
 */
trait TemporalUpdatedTrait
{
    /**
     * @ORM\Column(type="datetime", name="updated_at", nullable=true)
     * @var ?\DateTime
     */
    protected ?\DateTime $updatedAt;

    /** @ORM\PreUpdate */
    public function preUpdateHook()
    {
        $this->setUpdatedAt(new \DateTime());
    }

    /**
     * @param ?\DateTime $updatedAt
     * @return $this
     */
    public function setUpdatedAt(?\DateTime $updatedAt)
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUpdatedAt()
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