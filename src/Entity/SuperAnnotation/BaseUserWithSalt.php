<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAnnotation;

use Serializable;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

trigger_error('BaseUserWithSalt is deprecated.', E_USER_DEPRECATED);

class BaseUserWithSalt implements UserInterface, PasswordAuthenticatedUserInterface, Serializable
{
    public function __serialize(): array
    {
        return [
            $this->id,
            $this->username,
            $this->password,
            $this->salt
        ];
    }

    public function __unserialize(array $data): void
    {
        [
            $this->id,
            $this->username,
            $this->password,
            $this->salt
        ]
            = $data;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getSalt(): ?string
    {
        return $this->salt;
    }

    public function getRoles(): array
    {
        return $this->role;
    }

    public function eraseCredentials(): void
    {
        $this->password = null;
    }

    /** @see \Serializable::serialize() */
    public function serialize(): ?string
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

    public function getUserIdentifier(): string
    {
        return $this->getUsername();
    }
}
