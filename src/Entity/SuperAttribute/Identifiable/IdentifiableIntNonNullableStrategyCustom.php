<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Identifiable;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Serializer\Attribute\Groups;

trait IdentifiableIntNonNullableStrategyCustom
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, nullable: false)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[Groups([AuroraConstants::GROUP_READ])]
    protected string $id;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function generateId(): self
    {
        $uuid     = Uuid::v7();
        $this->id = $uuid->toHex();
        return $this;
    }
}
