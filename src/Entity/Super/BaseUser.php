<?php

namespace Sindla\Bundle\AuroraBundle\Entity\Super;

use Symfony\Component\Security\Core\User\UserInterface;

class BaseUser implements UserInterface, \Serializable
{
    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function getSalt(): ?string
    {
        // you *may* need a real salt depending on your encoder
        // see section on salt below
        return null;
    }

    public function getRoles(): array
    {
        return $this->role;
    }

    public function eraseCredentials()
    {
    }

    /** @see \Serializable::serialize() */
    public function serialize()
    {
        return serialize([
            $this->id,
            $this->username,
            $this->password,
            // see section on salt below
            // $this->salt,
        ]);
    }

    /** @see \Serializable::unserialize() */
    public function unserialize($serialized)
    {
        [
            $this->id,
            $this->username,
            $this->password,
            // see section on salt below
            // $this->salt
        ]
            = unserialize($serialized);
    }
}