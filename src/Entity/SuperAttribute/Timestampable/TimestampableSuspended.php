<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable;

use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;

trait TimestampableSuspended
{
    #[ORM\Column(name: 'suspended_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([AuroraConstants::GROUP_READ])]
    protected ?DateTimeInterface $suspendedAt = null;
    public function getSuspendedAt(): ?DateTimeInterface
    {
        return $this->suspendedAt;
    }

    public function setSuspendedAt(?DateTimeInterface $suspendedAt): self
    {
        $this->suspendedAt = $suspendedAt;
        return $this;
    }

    // ----------------------------------------------------------------------------------------------------------------------------------------------
    // -- CUSTOM METHODS ----------------------------------------------------------------------------------------------------------------------------

    public function getSuspendedInTheFuture(): bool
    {
        return $this->suspendedAt && $this->suspendedAt->getTimestamp() > (new \DateTime())->getTimestamp();
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getUpdatedAtLifespanAsSeconds(): int
    {
        return $this->suspendedAt ? (new \DateTime())->getTimestamp() - $this->suspendedAt->getTimestamp() : 0;
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getUpdatedAtLifespanAsMinutes(): int
    {
        return round($this->getUpdatedAtLifespanAsSeconds() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getUpdatedAtLifespanAsHours(): int
    {
        return round($this->getUpdatedAtLifespanAsMinutes() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getUpdatedAtLifespanAsDays(): int
    {
        return round($this->getUpdatedAtLifespanAsHours() / 24);
    }
}