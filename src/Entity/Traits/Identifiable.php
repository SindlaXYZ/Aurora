<?php
namespace Sindla\Bundle\BorealisBundle\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

trait Identifiable
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     *
     * strategy = AUTO      => SEQUENCE
     * strategy = IDENTITY  => SERIAL
     */
    protected $id;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
        return $this;
    }
}