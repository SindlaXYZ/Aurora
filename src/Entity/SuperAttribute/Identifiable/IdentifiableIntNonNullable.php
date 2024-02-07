<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Identifiable;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;

trait IdentifiableIntNonNullable
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\Column(type: Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[Groups([AuroraConstants::GROUP_READ])]
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
