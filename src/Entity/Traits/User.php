<?php

namespace Sindla\Bundle\BorealisBundle\Entity\Traits;

use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface, \Serializable
{
    /**
     * @var string
     */
    protected $flag;

    protected function isFlagSet($flag)
    {
        return (($this->flags & $flag) == $flag);
    }

    protected function setFlag($flag, $value)
    {
        if ($value) {
            $this->flags |= $flag;
        } else {
            $this->flags &= ~$flag;
        }
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function getSalt()
    {
        // you *may* need a real salt depending on your encoder
        // see section on salt below
        return null;
    }

    public function setSalt(string $salt)
    {
        $this->salt = $salt;
    }

    public function getRoles()
    {
        return ['ROLE_SONATA_ADMIN', 'ROLE_ADMIN', 'ROLE_USER'];
    }

    public function eraseCredentials()
    {
    }

    /** @see \Serializable::serialize() */
    public function serialize()
    {
        return serialize(array(
            $this->id,
            $this->username,
            $this->password,
            // see section on salt below
            // $this->salt,
        ));
    }

    /** @see \Serializable::unserialize() */
    public function unserialize($serialized)
    {
        list (
            $this->id,
            $this->username,
            $this->password,
            // see section on salt below
            // $this->salt
            )
            = unserialize($serialized);
    }
}