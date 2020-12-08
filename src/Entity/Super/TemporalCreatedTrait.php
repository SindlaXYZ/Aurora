<?php

namespace Sindla\Bundle\AuroraBundle\Entity\Super;

// Doctrine
use Doctrine\ORM\Mapping as ORM;

/**
 * Trait TemporalCreatedTrait
 *  + `createdAt`
 */
trait TemporalCreatedTrait
{
    /**
     * @ORM\Column(type="datetime", name="created_at", nullable=true)
     * @var ?\DateTime
     */
    protected ?\DateTime $createdAt;

    /** @ORM\PrePersist */
    public function prePersistHook()
    {
        $this->setCreatedAt(new \DateTime());

        if (method_exists($this, 'setUpdatedAt')) {
            $this->setUpdatedAt(new \DateTime());
        }
    }

    /**
     * @param ?\DateTime $createdAt
     * @return $this
     */
    public function setCreatedAt(?\DateTime $createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return ?\DateTime
     */
    public function getCreatedAt(): ?\DateTime
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