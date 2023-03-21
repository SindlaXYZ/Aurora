<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Identifiable;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait IntNonNullableNonAutoincrement
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER, nullable: false, options: ['`unsigned`' => true])]
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
