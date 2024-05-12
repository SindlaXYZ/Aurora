<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;

trait TimestampableDeletedMutable
{
    /**
     * Should have a default value (a date in the future), so that it can be used in a composed uniq key
     * If this is null and is used in a composed uniq key, then it will not work as expected
     * I.e.: two identical records can exist if the deleted_at is null, and the rest of the composer keys are the same
     * I.e.: two identical records CANNOT exist if the deleted_at is a date in the future, and the rest of the composer keys are the same
     */
    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_MUTABLE, nullable: true, options: ['default' => AuroraConstants::TIMESTAMPABLE_DELETED_DEFAULT_DELETED_AT])]
    private ?DateTime $deletedAt = null;

    public function getDeletedAt(): ?DateTime
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTime $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    // ----------------------------------------------------------------------------------------------------------------------------------------------
    // -- CUSTOM METHODS ----------------------------------------------------------------------------------------------------------------------------

    public function isDeleted(): bool
    {
        return !(null == $this->deletedAt || $this->deletedAt == AuroraConstants::TIMESTAMPABLE_DELETED_DEFAULT_DELETED_AT);
    }

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
