<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable;

use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;

trait TimestampableDeleted
{
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['default' => AuroraConstants::TIMESTAMPABLE_DELETED_DEFAULT_DELETED_AT])]
    private ?DateTimeInterface $deletedAt = null;

    public function getDeletedAt(): ?DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeInterface $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    // ----------------------------------------------------------------------------------------------------------------------------------------------
    // -- CUSTOM METHODS ----------------------------------------------------------------------------------------------------------------------------

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getDeletedAtLifespanAsSeconds(): int
    {
        return $this->deletedAt ? (new \DateTime())->getTimestamp() - $this->deletedAt->getTimestamp() : 0;
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getDeletedAtLifespanAsMinutes(): int
    {
        return round($this->getDeletedAtLifespanAsSeconds() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getDeletedAtLifespanAsHours(): int
    {
        return round($this->getDeletedAtLifespanAsMinutes() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getDeletedAtLifespanAsDays(): int
    {
        return round($this->getDeletedAtLifespanAsHours() / 24);
    }
}
