<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;

trait TimestampablePasswordUpdated
{
    #[ORM\Column(name: 'password_updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([AuroraConstants::GROUP_READ])]
    protected ?DateTimeInterface $passwordUpdatedAt = null;

    public function getPasswordUpdatedAt(): ?DateTimeInterface
    {
        return $this->passwordUpdatedAt;
    }

    public function setPasswordUpdatedAt(?DateTimeInterface $passwordUpdatedAt): self
    {
        $this->passwordUpdatedAt = $passwordUpdatedAt;
        return $this;
    }

    // ----------------------------------------------------------------------------------------------------------------------------------------------
    // -- CUSTOM METHODS ----------------------------------------------------------------------------------------------------------------------------

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getPasswordUpdatedAtLifespanAsSeconds(): int
    {
        return $this->passwordUpdatedAt ? new \DateTime()->getTimestamp() - $this->passwordUpdatedAt->getTimestamp() : 0;
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getPasswordUpdatedAtLifespanAsMinutes(): int
    {
        return round($this->getPasswordUpdatedAtLifespanAsSeconds() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getPasswordUpdatedAtLifespanAsHours(): int
    {
        return round($this->getPasswordUpdatedAtLifespanAsMinutes() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getPasswordUpdatedAtLifespanAsDays(): int
    {
        return round($this->getPasswordUpdatedAtLifespanAsHours() / 24);
    }
}
