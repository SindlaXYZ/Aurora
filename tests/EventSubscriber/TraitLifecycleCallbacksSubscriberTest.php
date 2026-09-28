<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\EventSubscriber;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableCreated;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableUpdated;
use Sindla\Bundle\AuroraBundle\EventSubscriber\TraitLifecycleCallbacksSubscriber;

#[RequiresPhpExtension('pdo_sqlite')]
class TraitLifecycleCallbacksSubscriberTest extends TestCase
{
    private EntityManager $em;

    protected function setUp(): void
    {
        $config = method_exists(ORMSetup::class, 'createAttributeMetadataConfig')
            ? ORMSetup::createAttributeMetadataConfig([], true)
            : ORMSetup::createAttributeMetadataConfiguration([], true);

        if (PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        $this->em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        $this->em->getEventManager()->addEventListener(Events::loadClassMetadata, new TraitLifecycleCallbacksSubscriber());

        new SchemaTool($this->em)->createSchema([$this->em->getClassMetadata(TraitLifecycleCallbacksArticle::class)]);
    }

    public function testTraitCallbacksRunWithoutHasLifecycleCallbacksOnTheEntity(): void
    {
        $article = new TraitLifecycleCallbacksArticle();
        $this->em->persist($article);
        $this->em->flush();

        // #[ORM\HasLifecycleCallbacks] on the trait used to be ignored: createdAt stayed null
        $this->assertInstanceOf(\DateTimeImmutable::class, $article->getCreatedAt());
        $this->assertNull($article->getUpdatedAt());

        $article->title = 'Updated';
        $this->em->flush();

        $this->assertInstanceOf(\DateTimeImmutable::class, $article->getUpdatedAt());

        $this->em->clear();
        $reloaded = $this->em->find(TraitLifecycleCallbacksArticle::class, $article->id);
        $this->assertNotNull($reloaded?->getCreatedAt());
        $this->assertNotNull($reloaded?->getUpdatedAt());
    }

    public function testCallbacksAreRegisteredOnceWhenTheEntityHasLifecycleCallbacks(): void
    {
        $metadata = $this->em->getClassMetadata(TraitLifecycleCallbacksWithAttributeArticle::class);

        $this->assertSame(['prePersistCreatedAt'], $metadata->lifecycleCallbacks[Events::prePersist]);
    }

    public function testAnOverriddenTraitMethodWithoutTheAttributeIsNotACallback(): void
    {
        $metadata = $this->em->getClassMetadata(TraitLifecycleCallbacksOverridingArticle::class);

        $this->assertArrayNotHasKey(Events::prePersist, $metadata->lifecycleCallbacks);
        $this->assertSame(['preUpdateUpdatedAt'], $metadata->lifecycleCallbacks[Events::preUpdate]);
    }

    public function testFollowsTheTraitsOfTheParentClassesAndTheAuroraTraitsNestedInOtherTraits(): void
    {
        $metadata = $this->em->getClassMetadata(TraitLifecycleCallbacksChildArticle::class);

        // prePersistLocal() of the application trait is not an Aurora callback: the entity needs its own #[ORM\HasLifecycleCallbacks]
        $this->assertSame(['prePersistCreatedAt'], $metadata->lifecycleCallbacks[Events::prePersist]);
        $this->assertSame(['preUpdateUpdatedAt'], $metadata->lifecycleCallbacks[Events::preUpdate]);
    }

    public function testAnEmbeddableGetsNoCallback(): void
    {
        // Doctrine throws a MappingException when a lifecycle callback is added to an embeddable
        $metadata = $this->em->getClassMetadata(TraitLifecycleCallbacksEmbeddable::class);

        $this->assertTrue($metadata->isEmbeddedClass);
        $this->assertSame([], $metadata->lifecycleCallbacks);
    }
}

#[ORM\Entity]
#[ORM\Table(name: 'trait_lifecycle_callbacks_article')]
class TraitLifecycleCallbacksArticle
{
    use TimestampableCreated;
    use TimestampableUpdated;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;

    #[ORM\Column(length: 20)]
    public string $title = 'Title';
}

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class TraitLifecycleCallbacksWithAttributeArticle
{
    use TimestampableCreated;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;
}

#[ORM\Entity]
class TraitLifecycleCallbacksOverridingArticle
{
    use TimestampableCreated;
    use TimestampableUpdated;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;

    public function prePersistCreatedAt(): void
    {
    }
}

abstract class TraitLifecycleCallbacksPlainParent
{
    use TimestampableUpdated;
}

trait TraitLifecycleCallbacksApplicationTrait
{
    use TimestampableCreated;

    #[ORM\PrePersist]
    public function prePersistLocal(): void
    {
    }
}

#[ORM\Entity]
class TraitLifecycleCallbacksChildArticle extends TraitLifecycleCallbacksPlainParent
{
    use TraitLifecycleCallbacksApplicationTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;
}

#[ORM\Embeddable]
class TraitLifecycleCallbacksEmbeddable
{
    use TimestampableCreated;
}
