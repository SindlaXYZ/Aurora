<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Identifiable;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;

trait LegacyStringNullable
{
    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Groups([AuroraConstants::GROUP_READ])]
    protected ?string $legacyIdentifier = null;

    public function getLegacyIdentifier(): ?string
    {
        return $this->legacyIdentifier;
    }

    public function setLegacyIdentifier(?string $legacyIdentifier = null): self
    {
        $this->legacyIdentifier = $legacyIdentifier;
        return $this;
    }
}
