<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\EventSubscriber;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping as ORM;

/**
 * Registers the lifecycle callbacks (#[ORM\PrePersist], #[ORM\PreUpdate], ...) declared by the Aurora entity traits,
 * e.g. TimestampableCreated::prePersistCreatedAt() or TimestampableUpdated::preUpdateUpdatedAt().
 *
 * Doctrine reads them only when the entity class itself has #[ORM\HasLifecycleCallbacks]: PHP attributes are not inherited
 * from traits, so the #[ORM\HasLifecycleCallbacks] declared on the traits was ignored and createdAt / updatedAt / deletedAt
 * were never set for the entities that did not repeat it.
 */
class TraitLifecycleCallbacksSubscriber
{
    private const string TRAITS_NAMESPACE = 'Sindla\\Bundle\\AuroraBundle\\Entity\\';

    private const array CALLBACK_EVENTS = [
        ORM\PrePersist::class  => Events::prePersist,
        ORM\PostPersist::class => Events::postPersist,
        ORM\PreUpdate::class   => Events::preUpdate,
        ORM\PostUpdate::class  => Events::postUpdate,
        ORM\PreRemove::class   => Events::preRemove,
        ORM\PostRemove::class  => Events::postRemove,
        ORM\PostLoad::class    => Events::postLoad,
        ORM\PreFlush::class    => Events::preFlush,
    ];

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $metadata = $eventArgs->getClassMetadata();

        if ($metadata->isEmbeddedClass) {
            return;
        }

        $class = $metadata->getReflectionClass();

        foreach ($this->auroraTraitMethodNames($class) as $methodName) {
            // The method of the class: a method that overrides the trait one (without the attribute) must not become a callback
            foreach ($class->getMethod($methodName)->getAttributes() as $attribute) {
                if (isset(self::CALLBACK_EVENTS[$attribute->getName()])) {
                    // A callback that is already registered (the class has #[ORM\HasLifecycleCallbacks]) is not added twice
                    $metadata->addLifecycleCallback($methodName, self::CALLBACK_EVENTS[$attribute->getName()]);
                }
            }
        }
    }

    /**
     * The public methods of the Aurora entity traits used by the class, by its parents and by the traits themselves
     *
     * @param \ReflectionClass<*> $class
     *
     * @return list<string>
     */
    private function auroraTraitMethodNames(\ReflectionClass $class): array
    {
        $methodNames = [];

        for ($current = $class; false !== $current; $current = $current->getParentClass()) {
            $traits = $current->getTraits();

            while ($trait = array_shift($traits)) {
                array_push($traits, ...array_values($trait->getTraits()));

                if (!str_starts_with($trait->getName(), self::TRAITS_NAMESPACE)) {
                    continue;
                }

                foreach ($trait->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                    if ($class->hasMethod($method->getName()) && $class->getMethod($method->getName())->isPublic()) {
                        $methodNames[$method->getName()] = $method->getName();
                    }
                }
            }
        }

        return array_values($methodNames);
    }
}
