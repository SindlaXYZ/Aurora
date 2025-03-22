<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;

trait TimestampableResponded
{
    #[ORM\Column(name: 'responded_at', type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['default' => null])]
    #[Groups([AuroraConstants::GROUP_READ])]
    private ?\DateTimeImmutable $respondedAt = null;

    public function getRespondedAt(): ?\DateTimeImmutable
    {
        return $this->respondedAt;
    }

    public function setRespondedAt(?\DateTimeImmutable $respondedAt): self
    {
        $this->respondedAt = $respondedAt;
        return $this;
    }

    // ----------------------------------------------------------------------------------------------------------------------------------------------
    // -- CUSTOM METHODS ----------------------------------------------------------------------------------------------------------------------------

    public function isResponded(): bool
    {
        return boolval($this->respondedAt);
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getRespondedAtLifespanAsSeconds(): int
    {
        return $this->respondedAt ? (new \DateTime())->getTimestamp() - $this->respondedAt->getTimestamp() : 0;
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getRespondedAtLifespanAsMinutes(): int
    {
        return round($this->getRespondedAtLifespanAsSeconds() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getRespondedAtLifespanAsHours(): int
    {
        return round($this->getRespondedAtLifespanAsMinutes() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getRespondedAtLifespanAsDays(): int
    {
        return round($this->getRespondedAtLifespanAsHours() / 24);
    }
}
