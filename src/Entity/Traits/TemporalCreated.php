<?php
namespace Sindla\Bundle\BorealisBundle\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

/**
 * Trait TemporalCreated
 * + `createdAt`
 */
trait TemporalCreated
{
    /**
     * @ORM\Column(type="datetime", name="created_at", nullable=true)
     * @var \DateTime
     */
    protected $createdAt;

    /** @ORM\PrePersist */
    public function prePersistHook()
    {
        $this->setCreatedAt(new \DateTime());
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
}