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
    #[ORM\Column(type: Types::INTEGER, nullable: false, options: ['unsigned' => true])]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
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

    public function generateId(): self
    {
        $uuid      = Uuid::v7();
        $hexString = $uuid->toHex();
        $bigInt    = intval(base_convert($uuid->toHex(), 16, 10));

        $this->id = $bigInt;
        return $this;
    }
}
