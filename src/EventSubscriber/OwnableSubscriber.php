<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\EventSubscriber;

use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Updates owner_id, created_by and updated_by when creating or updating a resource, only if the resource uses Ownable trait
 *
 * services.yaml:
 *
 * Sindla\Bundle\AuroraBundle\EventSubscriber\OwnableSubscriber:
 * arguments: [ "@security.token_storage" ]
 * tags:
 * - { name: doctrine.event_listener, event: prePersist, connection: default }
 * - { name: doctrine.event_listener, event: preUpdate, connection: default }
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

        // Without a user (console commands, Messenger workers, fixtures) the owner, created_by and updated_by that were set
        // explicitly used to be overwritten with null
        if (!($user = $this->getUser()) instanceof UserInterface) {
            return;
        }

        // An owner / a creator set explicitly (e.g. an admin creating a resource for another user) is kept
        foreach (['Owner', 'CreatedBy'] as $property) {
            if (method_exists($entity, "set{$property}") && !$this->isSet($entity, $property)) {
                $entity->{"set{$property}"}($user);
            }
        }

        if (method_exists($entity, 'setUpdatedBy')) {
            $entity->setUpdatedBy($user);
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

        if (method_exists($entity, 'setUpdatedBy') && ($user = $this->getUser()) instanceof UserInterface) {
            $entity->setUpdatedBy($user);
        }
    }

    private function isSet(object $entity, string $property): bool
    {
        if (!method_exists($entity, "get{$property}")) {
            return false;
        }

        try {
            return null !== $entity->{"get{$property}"}();
        } catch (\Error) {
            // A typed property that is not initialized yet
            return false;
        }
    }

    /**
     * TokenInterface::isAuthenticated() was removed in Symfony 6.0: a token that exposes a user is an authenticated one.
     */
    private function getUser(): ?UserInterface
    {
        return $this->tokenStorage->getToken()?->getUser();
    }
}
