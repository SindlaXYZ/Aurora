<?php

namespace Sindla\Bundle\AuroraBundle\Entity\Super;

// Doctrine
use Doctrine\ORM\Mapping as ORM;

/**
 * Trait TemporalCrondTrait
 *  + `crondAt`
 */
trait TemporalCrondTrait
{
    /**
     * @ORM\Column(type="datetime", name="crond_at", nullable=true)
     * @var ?\DateTime
     */
    protected ?\DateTime $crondAt = null;

    /**
     * @param ?\DateTime $crondAt
     * @return $this
     */
    public function setCrondAt(?\DateTime $crondAt)
    {
        $this->crondAt = $crondAt;
        return $this;
    }

    /**
     * @return ?\DateTime
     */
    public function getCrondAt(): ?\DateTime
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