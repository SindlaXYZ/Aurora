<?php
namespace Sindla\Bundle\BorealisBundle\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

/**
 * Trait TemporalCrondTrait
 */
trait TemporalCrond
{
    /**
     * @ORM\Column(type="datetime", name="crond_at", nullable=true)
     * @var \DateTime
     */
    protected $crondAt;

    /**
     * @param mixed $crondAt
     * @return $this
     */
    public function setCrondAt($crondAt)
    {
        $this->crondAt = $crondAt;
        return $this->crondAt;
    }

    /**
     * @return mixed
     */
    public function getCrondAt()
    {
        return $this->crondAt;
    }
}