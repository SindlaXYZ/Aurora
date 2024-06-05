<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Identifiable;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;

trait LegacyIntNullable
{
    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    #[Groups([AuroraConstants::GROUP_READ])]
    protected ?int $legacyId = null;

    public function getLegacyId(): ?int
    {
        return $this->legacyId;
    }

    public function setLegacyId(?int $legacyId = null): self
    {
        $this->legacyId = $legacyId;
        return $this;
    }
}
