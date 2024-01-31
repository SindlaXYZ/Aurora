<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAnnotation;

// Core
use Serializable;

// Symfony
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

trigger_error('BaseUserWithSalt is deprecated.', E_USER_DEPRECATED);

class BaseUser implements UserInterface, PasswordAuthenticatedUserInterface, Serializable
{
    public function __serialize(): array {}
    public function __unserialize(array $data): void {}

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @return string|null
     */
    public function getPassword(): ?string
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

    public function eraseCredentials(): void
    {
    }

    /** @see \Serializable::serialize() */
    public function serialize(): ?string
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

    /**
     * @return string
     */
    public function getUserIdentifier(): string
    {
        return $this->getUsername();
    }
}
