<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable;

use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;

trait TimestampableSuspendedInterval
{
    #[ORM\Column(name: 'suspended_from', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([AuroraConstants::GROUP_READ, AuroraConstants::GROUP_READ_TIMESTAMPABLE])]
    protected ?DateTimeInterface $suspendedFrom = null;

    #[ORM\Column(name: 'suspended_to', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([AuroraConstants::GROUP_READ, AuroraConstants::GROUP_READ_TIMESTAMPABLE])]
    protected ?DateTimeInterface $suspendedTo = null;

    public function getSuspendedFrom(): ?DateTimeInterface
    {
        return $this->suspendedFrom;
    }

    public function setSuspendedFrom(?DateTimeInterface $suspendedFrom): self
    {
        $this->suspendedFrom = $suspendedFrom;
        return $this;
    }

    public function getSuspendedTo(): ?DateTimeInterface
    {
        return $this->suspendedTo;
    }

    public function setSuspendedTo(?DateTimeInterface $suspendedTo): self
    {
        $this->suspendedTo = $suspendedTo;
        return $this;
    }

    // ----------------------------------------------------------------------------------------------------------------------------------------------
    // -- CUSTOM METHODS ----------------------------------------------------------------------------------------------------------------------------

    #[Groups([AuroraConstants::GROUP_READ, AuroraConstants::GROUP_READ_TIMESTAMPABLE])]
    public function isSuspended(): bool
    {
        return
            ($this->getSuspendedFrom() && !$this->getSuspendedTo() && $this->getSuspendedFrom()->getTimestamp() <= time())
            ||
            (!$this->getSuspendedFrom() && $this->getSuspendedTo() && $this->getSuspendedTo()->getTimestamp() >= time())
            ||
            ($this->getSuspendedFrom() && $this->getSuspendedTo() && $this->getSuspendedFrom()->getTimestamp() <= time() && $this->getSuspendedTo()->getTimestamp() >= time());
    }

    #[Groups([AuroraConstants::GROUP_READ, AuroraConstants::GROUP_READ_TIMESTAMPABLE])]
    public function getSuspendedInTheFuture(): bool
    {
        return $this->getSuspendedFrom() && $this->getSuspendedFrom()->getTimestamp() > time();
    }

    #[Groups([AuroraConstants::GROUP_READ, AuroraConstants::GROUP_READ_TIMESTAMPABLE])]
    public function getSuspendedAtLifespanAsSeconds(): int
    {
        if ($this->getSuspendedFrom() && $this->getSuspendedFrom()->getTimestamp() <= time()) {
            return time() - $this->getSuspendedFrom()->getTimestamp();
        } else if (!$this->getSuspendedFrom() && $this->getSuspendedTo() && $this->getSuspendedTo()->getTimestamp() >= time()) {
            return $this->getSuspendedTo()->getTimestamp() - time();
        } else if ($this->getSuspendedFrom() && $this->getSuspendedTo() && $this->getSuspendedFrom()->getTimestamp() <= time() && $this->getSuspendedTo()->getTimestamp() >= time()) {
            return $this->getSuspendedTo()->getTimestamp() - $this->getSuspendedFrom()->getTimestamp();
        }

        return 0;
    }

    #[Groups([AuroraConstants::GROUP_READ, AuroraConstants::GROUP_READ_TIMESTAMPABLE])]
    public function getSuspendedAtLifespanAsMinutes(): int
    {
        return round($this->getSuspendedAtLifespanAsSeconds() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ, AuroraConstants::GROUP_READ_TIMESTAMPABLE])]
    public function getSuspendedAtLifespanAsHours(): int
    {
        return round($this->getSuspendedAtLifespanAsMinutes() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ, AuroraConstants::GROUP_READ_TIMESTAMPABLE])]
    public function getSuspendedAtLifespanAsDays(): int
    {
        return round($this->getSuspendedAtLifespanAsHours() / 24);
    }
}
