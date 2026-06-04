<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperClass;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
abstract class AbstractTimestampableDeletedNotNullable
{
    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['default' => AuroraConstants::TIMESTAMPABLE_DELETED_DEFAULT_DELETED_AT])]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\PrePersist]
    public function prePersistDeletedAt(): void
    {
        if (!$this->deletedAt) {
            $this->setDeletedAt(new \DateTimeImmutable(AuroraConstants::TIMESTAMPABLE_DELETED_DEFAULT_DELETED_AT));
        }
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    // ----------------------------------------------------------------------------------------------------------------------------------------------
    // -- CUSTOM METHODS ----------------------------------------------------------------------------------------------------------------------------

    #[Groups([AuroraConstants::GROUP_READ])]
    public function isDeleted(): bool
    {
        return $this->deletedAt && $this->deletedAt->getTimestamp() < time();
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function isDeletedInFuture(): bool
    {
        return $this->deletedAt && $this->deletedAt->format('Y-m-d H:i:s') != new \DateTimeImmutable(AuroraConstants::TIMESTAMPABLE_DELETED_DEFAULT_DELETED_AT)->format('Y-m-d H:i:s')
        && $this->deletedAt->getTimestamp() >= time();
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getDeletedAtLifespanAsSeconds(): int
    {
        return $this->deletedAt ? new \DateTime()->getTimestamp() - $this->deletedAt->getTimestamp() : 0;
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getDeletedAtLifespanAsMinutes(): int
    {
        return (int)round($this->getDeletedAtLifespanAsSeconds() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getDeletedAtLifespanAsHours(): int
    {
        return (int)round($this->getDeletedAtLifespanAsMinutes() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getDeletedAtLifespanAsDays(): int
    {
        return (int)round($this->getDeletedAtLifespanAsHours() / 24);
    }
}
