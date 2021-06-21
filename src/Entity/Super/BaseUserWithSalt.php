<?php

namespace Sindla\Bundle\AuroraBundle\Entity\Super;

use Symfony\Component\Security\Core\User\UserInterface;

class BaseUserWithSalt implements UserInterface, \Serializable
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

    /**
     * @return string
     */
    public function getSalt(): ?string
    {
        return $this->salt;
    }

    /**
     * @return array
     */
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
            $this->salt
        ]);
    }

    /** @see \Serializable::unserialize() */
    public function unserialize($serialized)
    {
        [
            $this->id,
            $this->username,
            $this->password,
            $this->salt
        ]
            = unserialize($serialized);
    }

    /**
     * @return string
     */
    public function getUserIdentifier(): string
    {
        return $this->getUsername();
    }
}