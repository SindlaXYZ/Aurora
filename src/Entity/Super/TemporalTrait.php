<?php

namespace Sindla\Bundle\AuroraBundle\Entity\Super;

// Doctrine
use Doctrine\ORM\Mapping as ORM;

// Aurora
use Sindla\Bundle\AuroraBundle\Entity\Super\TemporalCreatedTrait;
use Sindla\Bundle\AuroraBundle\Entity\Super\TemporalUpdatedTrait;

/**
 * Trait TemporalTrait
 * + `createdAt`
 * + `updatedAt`
 */
trait TemporalTrait
{
    use TemporalCreatedTrait;
    use TemporalUpdatedTrait;
}
