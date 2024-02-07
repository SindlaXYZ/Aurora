<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;

trait TimestampableUpdated
{
    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([AuroraConstants::GROUP_READ])]
    protected ?DateTimeInterface $updatedAt = null;

    #[ORM\PreUpdate]
    public function preUpdateHook(): void
    {
        $this->setUpdatedAt(new DateTimeImmutable());

        if (method_exists($this, 'getCreatedAt') && null === $this->getCreatedAt()) {
            $this->setCreatedAt($this->getUpdatedAt());
        }
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // ----------------------------------------------------------------------------------------------------------------------------------------------
    // -- CUSTOM METHODS ----------------------------------------------------------------------------------------------------------------------------

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getUpdatedAtLifespanAsSeconds(): int
    {
        return $this->updatedAt ? (new \DateTime())->getTimestamp() - $this->updatedAt->getTimestamp() : 0;
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
