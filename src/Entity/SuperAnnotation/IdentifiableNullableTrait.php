<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAnnotation;

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
     * AUTO
     *   Tells Doctrine to pick the strategy that is preferred by the used database platform.
     *   The preferred strategies are IDENTITY for MySQL, SQLite and MsSQL and SEQUENCE for Oracle and PostgreSQL. This strategy provides full portability.
     *
     * SEQUENCE
     *   Tells Doctrine to use a database sequence for ID generation. This strategy does currently not provide full portability. Sequences are supported by Oracle and PostgreSql and SQL Anywhere.
     *
     * IDENTITY
     *   Tells Doctrine to use special identity columns in the database that generate a value on insertion of a row. This strategy does currently not provide full portability and is supported by the following platforms
     *      MySQL/SQLite/SQL Anywhere => AUTO_INCREMENT
     *      MSSQL => IDENTITY
     *      PostgreSQL => SERIAL
     */
    protected ?int $id = null;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }
}
