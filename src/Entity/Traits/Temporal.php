<?php
namespace Sindla\Bundle\BorealisBundle\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

/**
 * Trait TemporalTrait
 * + `createdAt`
 * + `updatedAt`
 */
trait Temporal
{
    /**
     * @ORM\Column(type="datetime", name="created_at", nullable=true)
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @ORM\Column(type="datetime", name="updated_at", nullable=true)
     * @var \DateTime
     */
    protected $updatedAt;

    /** @ORM\PrePersist */
    public function prePersistHook()
    {
        $this->setCreatedAt(new \DateTime());
        $this->setUpdatedAt(new \DateTime());
    }

    /** @ORM\PreUpdate */
    public function preUpdateHook()
    {
        $this->setUpdatedAt(new \DateTime());
    }

    /**
     * @param mixed $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this->createdAt;
    }

    /**
     * @return mixed
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $updatedAt
     * @return $this
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
        return $this->updatedAt;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
}