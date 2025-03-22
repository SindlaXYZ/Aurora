<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable;

use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;

trait TimestampableAvailableInterval
{
    #[ORM\Column(name: 'available_from', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([AuroraConstants::GROUP_READ])]
    protected ?DateTimeInterface $availableFrom = null;

    #[ORM\Column(name: 'available_to', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([AuroraConstants::GROUP_READ])]
    protected ?DateTimeInterface $availableTo = null;

    public function getAvailableFrom(): ?DateTimeInterface
    {
        return $this->availableFrom;
    }

    public function setAvailableFrom(?DateTimeInterface $availableFrom): self
    {
        $this->availableFrom = $availableFrom;
        return $this;
    }

    public function getAvailableTo(): ?DateTimeInterface
    {
        return $this->availableTo;
    }

    public function setAvailableTo(?DateTimeInterface $availableTo): self
    {
        $this->availableTo = $availableTo;
        return $this;
    }

    // ----------------------------------------------------------------------------------------------------------------------------------------------
    // -- CUSTOM METHODS ----------------------------------------------------------------------------------------------------------------------------

    #[Groups([AuroraConstants::GROUP_READ])]
    public function isAvailable(): bool
    {
        return
            ($this->getAvailableFrom() && !$this->getAvailableTo() && $this->getAvailableFrom()->getTimestamp() <= time())
            ||
            (!$this->getAvailableFrom() && $this->getAvailableTo() && $this->getAvailableTo()->getTimestamp() >= time())
            ||
            ($this->getAvailableFrom() && $this->getAvailableTo() && $this->getAvailableFrom()->getTimestamp() <= time() && $this->getAvailableTo()->getTimestamp() >= time());
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getAvailableInTheFuture(): bool
    {
        return $this->getAvailableFrom() && $this->getAvailableFrom()->getTimestamp() > time();
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getAvailableAtLifespanAsSeconds(): int
    {
        if ($this->getAvailableFrom() && $this->getAvailableFrom()->getTimestamp() <= time()) {
            return time() - $this->getAvailableFrom()->getTimestamp();
        } else if (!$this->getAvailableFrom() && $this->getAvailableTo() && $this->getAvailableTo()->getTimestamp() >= time()) {
            return $this->getAvailableTo()->getTimestamp() - time();
        } else if ($this->getAvailableFrom() && $this->getAvailableTo() && $this->getAvailableFrom()->getTimestamp() <= time() && $this->getAvailableTo()->getTimestamp() >= time()) {
            return $this->getAvailableTo()->getTimestamp() - $this->getAvailableFrom()->getTimestamp();
        }

        return 0;
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getAvailableAtLifespanAsMinutes(): int
    {
        return round($this->getAvailableAtLifespanAsSeconds() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getAvailableAtLifespanAsHours(): int
    {
        return round($this->getAvailableAtLifespanAsMinutes() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getAvailableAtLifespanAsDays(): int
    {
        return round($this->getAvailableAtLifespanAsHours() / 24);
    }
}
