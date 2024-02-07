<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;

trait TimestampableCroned
{
    #[ORM\Column(name: 'croned_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([AuroraConstants::GROUP_READ])]
    protected ?DateTimeInterface $cronedAt = null;

    public function getCronedAt(): ?DateTimeInterface
    {
        return $this->cronedAt;
    }

    public function setCronedAt(?DateTimeInterface $createdAt): self
    {
        $this->cronedAt = $createdAt;
        return $this;
    }

    // ----------------------------------------------------------------------------------------------------------------------------------------------
    // -- CUSTOM METHODS ----------------------------------------------------------------------------------------------------------------------------


    #[Groups([AuroraConstants::GROUP_READ])]
    public function getCronedAtLifespanAsSeconds(): int
    {
        return $this->cronedAt ? (new \DateTime())->getTimestamp() - $this->cronedAt->getTimestamp() : 0;
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getCronedAtLifespanAsMinutes(): int
    {
        return round($this->getCronedAtLifespanAsSeconds() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getCronedAtLifespanAsHours(): int
    {
        return round($this->getCronedAtLifespanAsMinutes() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getCronedAtLifespanAsDays(): int
    {
        return round($this->getCronedAtLifespanAsHours() / 24);
    }
}
