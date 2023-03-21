<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Identifiable;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait IdentifiableBigintNotNullableNonAutoincrement
{
    #[ORM\Id]
    #[ORM\Column(type: Types::BIGINT, nullable: false, options: ['`unsigned`' => true])]
    protected int $id;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }
}
