<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable;

use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;

trait TimestampableTranslated
{
    #[ORM\Column(name: 'translated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([AuroraConstants::GROUP_READ])]
    protected ?DateTimeInterface $translatedAt = null;

    public function getTranslatedAt(): ?DateTimeInterface
    {
        return $this->translatedAt;
    }

    public function setTranslatedAt(?DateTimeInterface $translatedAt): self
    {
        $this->translatedAt = $translatedAt;
        return $this;
    }

    // ----------------------------------------------------------------------------------------------------------------------------------------------
    // -- CUSTOM METHODS ----------------------------------------------------------------------------------------------------------------------------

    public function isCroned(): bool
    {
        return boolval($this->translatedAt);
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getTranslatedAtLifespanAsSeconds(): int
    {
        return $this->translatedAt ? (new \DateTime())->getTimestamp() - $this->translatedAt->getTimestamp() : 0;
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getTranslatedAtLifespanAsMinutes(): int
    {
        return round($this->getTranslatedAtLifespanAsSeconds() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getTranslatedAtLifespanAsHours(): int
    {
        return round($this->getTranslatedAtLifespanAsMinutes() / 60);
    }

    #[Groups([AuroraConstants::GROUP_READ])]
    public function getTranslatedAtLifespanAsDays(): int
    {
        return round($this->getTranslatedAtLifespanAsHours() / 24);
    }
}
