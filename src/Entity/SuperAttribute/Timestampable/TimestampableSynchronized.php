<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable;

use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;

trait TimestampableSynchronized
{
    #[ORM\Column(name: 'synchronized_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([AuroraConstants::GROUP_READ, AuroraConstants::GROUP_READ_TIMESTAMPABLE])]
    protected ?DateTimeInterface $synchronizedAt = null;

    public function getSynchronizedAt(): ?DateTimeInterface
    {
        return $this->synchronizedAt;
    }

    public function setSynchronizedAt(?DateTimeInterface $synchronizedAt): self
    {
        $this->synchronizedAt = $synchronizedAt;
        return $this;
    }

    // ----------------------------------------------------------------------------------------------------------------------------------------------
    // -- CUSTOM METHODS ----------------------------------------------------------------------------------------------------------------------------

    public function isSynchronized(): bool
    {
        return boolval($this->synchronizedAt);
    }

    #[Groups([AuroraConstants::GROUP_READ, AuroraConstants::GROUP_READ_TIMESTAMPABLE])]
    public function getSynchronizedAtLifespanAsSeconds(): int
    {
        return $this->synchronizedAt ? new \DateTime()->getTimestamp() - $this->synchronizedAt->getTimestamp() : 0;
    }

    #[Groups([AuroraConstants::GROUP_READ, AuroraConstants::GROUP_READ_TIMESTAMPABLE])]
    public function getSynchronizedAtLifespanAsMinutes(): int
    {
        return round($this->getSynchronizedAtLifespanAsSeconds() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ, AuroraConstants::GROUP_READ_TIMESTAMPABLE])]
    public function getSynchronizedAtLifespanAsHours(): int
    {
        return round($this->getSynchronizedAtLifespanAsMinutes() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ, AuroraConstants::GROUP_READ_TIMESTAMPABLE])]
    public function getSynchronizedAtLifespanAsDays(): int
    {
        return round($this->getSynchronizedAtLifespanAsHours() / 24);
    }
}
