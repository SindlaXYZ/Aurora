<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAnnotation;

// Doctrine
use Doctrine\ORM\Mapping as ORM;

// Aurora
use Sindla\Bundle\AuroraBundle\Entity\SuperAnnotation\TemporalCreatedTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAnnotation\TemporalUpdatedTrait;

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
