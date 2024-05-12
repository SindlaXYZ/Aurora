<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;

trait TimestampableCreated
{
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([AuroraConstants::GROUP_READ])]
    protected ?\DateTimeInterface $createdAt = null;

    #[ORM\PrePersist]
    public function prePersistHook(): void
    {
        if (!isset($this->createdAt)) {
            $this->setCreatedAt(new \DateTimeImmutable());
        }

        if (method_exists($this, 'setUpdatedAt') && !isset($this->updatedAt)) {
            $this->setUpdatedAt(new \DateTimeImmutable());
        }

        if (method_exists($this, 'setDeletedAt') && !isset($this->deletedAt)) {
            $attributes = (new \ReflectionClass($this))->getProperty('deletedAt')->getAttributes();
            foreach ($attributes as $attribute) {
                if ($attribute->getName() === Orm\Column::class) {
                    foreach ($attribute->getArguments() as $argument) {
                        if ('datetime' === $argument) {
                            $this->setDeletedAt(new \DateTime(AuroraConstants::TIMESTAMPABLE_DELETED_DEFAULT_DELETED_AT));
                            break;
                        } else if ('datetime_immutable' === $argument) {
                            $this->setDeletedAt(new \DateTimeImmutable(AuroraConstants::TIMESTAMPABLE_DELETED_DEFAULT_DELETED_AT));
                            break;
                        }
                    }
                }
            }
        }
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    // ----------------------------------------------------------------------------------------------------------------------------------------------
    // -- CUSTOM METHODS ----------------------------------------------------------------------------------------------------------------------------

    #[Groups([AuroraConstants::GROUP_READ])]
    public function isPersisted(): bool
    {
        return (bool)$this->createdAt;
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getCreatedAtLifespanAsSeconds(): int
    {
        return $this->createdAt ? (new \DateTime())->getTimestamp() - $this->createdAt->getTimestamp() : 0;
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getCreatedAtLifespanAsMinutes(): int
    {
        return round($this->getCreatedAtLifespanAsSeconds() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getCreatedAtLifespanAsHours(): int
    {
        return round($this->getCreatedAtLifespanAsMinutes() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getCreatedAtLifespanAsDays(): int
    {
        return round($this->getCreatedAtLifespanAsHours() / 24);
    }
}
