<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\EventSubscriber;

// Symfony
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

// Aurora
use Sindla\Bundle\AuroraBundle\Entity\SuperAnnotation;

/**
 * Updates owner_id, created_by and updated_by when creating or updating a resource, only if the resource uses Ownable trait
 *
 * services.yaml:
 *
    Sindla\Bundle\AuroraBundle\EventSubscriber\OwnableSubscriber:
        arguments: [ "@security.token_storage" ]
        tags:
            - { name: doctrine.event_listener, event: prePersist, connection: default }
            - { name: doctrine.event_listener, event: preUpdate, connection: default }
 */
class OwnableSubscriber implements EventSubscriberInterface
{
    private TokenStorageInterface $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate
        ];
    }

    /**
     * Before Create
     *
     * @param LifecycleEventArgs $args
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        /** @var Ownable $entity */
        $entity = $args->getObject();

        if (method_exists($entity, 'setOwner')) {
            $entity->setOwner($this->getUser());
        }

        if (method_exists($entity, 'setCreatedBy')) {
            $entity->setCreatedBy($this->getUser());
        }

        if (method_exists($entity, 'setUpdatedBy')) {
            $entity->setUpdatedBy($this->getUser());
        }
    }

    /**
     * Before Update
     *
     * @param LifecycleEventArgs $args
     */
    public function preUpdate(LifecycleEventArgs $args): void
    {
        /** @var Ownable $entity */
        $entity = $args->getObject();

        if (method_exists($entity, 'setUpdatedBy')) {
            $entity->setUpdatedBy($this->getUser());
        }
    }

    /**
     * @return UserInterface|User|null
     */
    private function getUser(): ?User
    {
        if (!$token = $this->tokenStorage->getToken()) {
            return null;
        }

        if (!$token->isAuthenticated()) {
            return null;
        }

        if (!$user = $token->getUser()) {
            return null;
        }

        return $user;
    }
}
