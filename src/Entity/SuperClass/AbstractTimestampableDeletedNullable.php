<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperClass;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\MappedSuperclass]
abstract class AbstractTimestampableDeletedNullable
{
    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['default' => null])]
    private ?\DateTimeImmutable $deletedAt = null;

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
        if (!$this->deletedAt) {
            return false;
        }

        $reflectionClass = new \ReflectionClass($this);
        $softDeleteableAttributes = $reflectionClass->getAttributes(Gedmo\SoftDeleteable::class);

        if (!empty($softDeleteableAttributes)) {
            $softDeleteable = $softDeleteableAttributes[0]->newInstance();
            if (!$softDeleteable->timeAware) {
                return true;
            }
        }

        return $this->deletedAt->getTimestamp() >= time();
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
