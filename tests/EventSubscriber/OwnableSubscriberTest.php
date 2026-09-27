<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\EventSubscriber;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\EventSubscriber\OwnableSubscriber;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * clear; php vendor/phpunit/phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/EventSubscriber/OwnableSubscriberTest.php --no-coverage
 */
class OwnableSubscriberTest extends TestCase
{
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

    public function testPreUpdateWithoutTokenSetsNoUser(): void
    {
        $entity = new OwnableEntityMock();

        new OwnableSubscriber(new TokenStorage())->preUpdate(new LifecycleEventArgs($entity, $this->createStub(ObjectManager::class)));

        $this->assertNull($entity->updatedBy);
    }
}

class OwnableEntityMock
{
    public ?UserInterface $owner     = null;
    public ?UserInterface $createdBy = null;
    public ?UserInterface $updatedBy = null;

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
