<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\EventSubscriber;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\EventSubscriber\OwnableSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * clear; php vendor/phpunit/phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/EventSubscriber/OwnableSubscriberTest.php --no-coverage
 */
class OwnableSubscriberTest extends TestCase
{
    public function testIsADoctrineListenerNotASymfonyEventSubscriber(): void
    {
        // Its Doctrine events were read as a map of Symfony events: an autoconfigured service listened to the kernel events "0" and "1"
        $this->assertNotInstanceOf(EventSubscriberInterface::class, new OwnableSubscriber(new TokenStorage()));
    }

    public function testPrePersistSetsTheAuthenticatedUser(): void
    {
        $user         = new InMemoryUser('aurora', null, ['ROLE_USER']);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $entity = new OwnableEntityMock();

        // Used to call TokenInterface::isAuthenticated() (removed in Symfony 6) and to return a non-existent "User" class
        new OwnableSubscriber($tokenStorage)->prePersist(new LifecycleEventArgs($entity, $this->createStub(ObjectManager::class)));

        $this->assertSame($user, $entity->owner);
        $this->assertSame($user, $entity->createdBy);
        $this->assertSame($user, $entity->updatedBy);
    }

    public function testWithoutTokenTheOwnerFieldsAreKept(): void
    {
        $owner  = new InMemoryUser('owner', null);
        $entity = new OwnableEntityMock();
        $entity->setOwner($owner)->setCreatedBy($owner)->setUpdatedBy($owner);

        $subscriber = new OwnableSubscriber(new TokenStorage());
        $args       = new LifecycleEventArgs($entity, $this->createStub(ObjectManager::class));

        // Console commands, Messenger workers, fixtures: the explicit values used to be overwritten with null
        $subscriber->prePersist($args);
        $subscriber->preUpdate($args);

        $this->assertSame($owner, $entity->owner);
        $this->assertSame($owner, $entity->createdBy);
        $this->assertSame($owner, $entity->updatedBy);
    }

    public function testPrePersistKeepsAnExplicitOwnerAndCreator(): void
    {
        $admin        = new InMemoryUser('admin', null, ['ROLE_ADMIN']);
        $customer     = new InMemoryUser('customer', null);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($admin, 'main', $admin->getRoles()));

        // An admin creates a resource for a customer
        $entity = new OwnableEntityMock();
        $entity->setOwner($customer);

        new OwnableSubscriber($tokenStorage)->prePersist(new LifecycleEventArgs($entity, $this->createStub(ObjectManager::class)));

        $this->assertSame($customer, $entity->owner);
        $this->assertSame($admin, $entity->createdBy);
        $this->assertSame($admin, $entity->updatedBy);
    }

    public function testPreUpdateWithoutTokenSetsNoUser(): void
    {
        $entity = new OwnableEntityMock();

        new OwnableSubscriber(new TokenStorage())->preUpdate(new LifecycleEventArgs($entity, $this->createStub(ObjectManager::class)));

        $this->assertNull($entity->updatedBy);
    }

    public function testPreUpdateSetsOnlyTheUpdater(): void
    {
        $owner  = new InMemoryUser('owner', null);
        $editor = new InMemoryUser('editor', null, ['ROLE_ADMIN']);
        $entity = new OwnableEntityMock();
        $entity->setOwner($owner)->setCreatedBy($owner)->setUpdatedBy($owner);

        new OwnableSubscriber($this->createTokenStorage($editor))->preUpdate(new LifecycleEventArgs($entity, $this->createStub(ObjectManager::class)));

        $this->assertSame($owner, $entity->owner);
        $this->assertSame($owner, $entity->createdBy);
        $this->assertSame($editor, $entity->updatedBy);
    }

    public function testPrePersistSetsTheOwnerOfAnEntityWithoutGetter(): void
    {
        $user   = new InMemoryUser('aurora', null);
        $entity = new class {
            public ?UserInterface $owner = null;

            public function setOwner(UserInterface $owner): void
            {
                $this->owner = $owner;
            }
        };

        new OwnableSubscriber($this->createTokenStorage($user))->prePersist(new LifecycleEventArgs($entity, $this->createStub(ObjectManager::class)));

        $this->assertSame($user, $entity->owner);
    }

    public function testPrePersistSetsAnUninitializedTypedOwner(): void
    {
        $user   = new InMemoryUser('aurora', null);
        $entity = new class {
            private UserInterface $owner;

            public function getOwner(): UserInterface
            {
                return $this->owner;
            }

            public function setOwner(UserInterface $owner): void
            {
                $this->owner = $owner;
            }
        };

        // getOwner() throws an Error before the property is initialized
        new OwnableSubscriber($this->createTokenStorage($user))->prePersist(new LifecycleEventArgs($entity, $this->createStub(ObjectManager::class)));

        $this->assertSame($user, $entity->getOwner());
    }

    private function createTokenStorage(UserInterface $user): TokenStorage
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        return $tokenStorage;
    }
}

class OwnableEntityMock
{
    public ?UserInterface $owner     = null;
    public ?UserInterface $createdBy = null;
    public ?UserInterface $updatedBy = null;

    public function getOwner(): ?UserInterface
    {
        return $this->owner;
    }

    public function getCreatedBy(): ?UserInterface
    {
        return $this->createdBy;
    }

    public function setOwner(?UserInterface $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function setCreatedBy(?UserInterface $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function setUpdatedBy(?UserInterface $updatedBy): self
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }
}
