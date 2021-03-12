<?php

namespace Sindla\Bundle\AuroraBundle\Entity\Super;

// Doctrine
use Doctrine\ORM\Mapping as ORM;

/**
 * Trait IdentifiableNullableTrait
 *  + `id`
 *
 * Toward `IdentifiableTrait`, this trait allow `id` to be null
 * A NON-null $id will generate an error in Doctrine:UnitOfWork when an EntityObject will be removed
 */
trait IdentifiableNullableTrait
{
    /**
     * @var ?int
     *
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     *
     * strategy = AUTO      => SEQUENCE
     * strategy = IDENTITY  => SERIAL
     */
    protected ?int $id = 0;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    public function setId(?int $id)
    {
        $this->id = $id;
        return $this;
    }
}