<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Identifiable;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;

trait IdentifiableIntNonNullableNonAutoincrement
{
    // Nullable: getId() on a new entity (e.g. "{% if entity.id %}" in a form template) or on a removed one used to be an Error, the
    // typed property "must not be accessed before initialization"
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER, nullable: false, options: ['`unsigned`' => true])]
    #[Groups([AuroraConstants::GROUP_READ, AuroraConstants::GROUP_READ_IDENTIFIABLE])]
    protected ?int $id = null;

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
