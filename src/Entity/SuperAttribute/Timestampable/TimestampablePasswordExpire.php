<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;

trait TimestampablePasswordExpire
{
    #[ORM\Column(name: 'password_expire_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([AuroraConstants::GROUP_READ, AuroraConstants::GROUP_READ_TIMESTAMPABLE])]
    protected ?DateTimeInterface $passwordExpireAt = null;

    public function getPasswordExpireAt(): ?DateTimeInterface
    {
        return $this->passwordExpireAt;
    }

    public function setPasswordExpireAt(?DateTimeInterface $passwordExpireAt): self
    {
        $this->passwordExpireAt = $passwordExpireAt;
        return $this;
    }

    // ----------------------------------------------------------------------------------------------------------------------------------------------
    // -- CUSTOM METHODS ----------------------------------------------------------------------------------------------------------------------------

    #[Groups([AuroraConstants::GROUP_READ, AuroraConstants::GROUP_READ_TIMESTAMPABLE])]
    public function getPasswordExpireAtLifespanAsSeconds(): int
    {
        return $this->passwordExpireAt ? new \DateTime()->getTimestamp() - $this->passwordExpireAt->getTimestamp() : 0;
    }

    #[Groups([AuroraConstants::GROUP_READ, AuroraConstants::GROUP_READ_TIMESTAMPABLE])]
    public function getPasswordExpireAtLifespanAsMinutes(): int
    {
        return round($this->getPasswordExpireAtLifespanAsSeconds() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ, AuroraConstants::GROUP_READ_TIMESTAMPABLE])]
    public function getPasswordExpireAtLifespanAsHours(): int
    {
        return round($this->getPasswordExpireAtLifespanAsMinutes() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ, AuroraConstants::GROUP_READ_TIMESTAMPABLE])]
    public function getPasswordExpireAtLifespanAsDays(): int
    {
        return round($this->getPasswordExpireAtLifespanAsHours() / 24);
    }
}
