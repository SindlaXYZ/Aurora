<?php

namespace Sindla\Bundle\AuroraBundle\Entity\Super;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

trait IdentifiableUUID
{
    /**
     * Generate random uniq id instead of incremental ids
     * The UUIDs are one-way encoded
     *
     * @var string
     *
     * @ORM\Id
     * @ORM\Column(type="uuid", name="id", nullable=false, unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="doctrine.uuid_generator")
     *
     * @link https://symfony.com/doc/current/components/uid.html
     */
    protected $id;

    /**
     * @return Uuid|null
     */
    public function getId(): ?Uuid
    {
        return $this->id;
    }
}
