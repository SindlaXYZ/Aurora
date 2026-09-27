<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Doctrine\Migrations\Factory;

// Symfony
use Symfony\Component\DependencyInjection\ContainerInterface;

// Doctrine
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Version\MigrationFactory;

/**
 * Injects the service container into the migrations that declare a public "setContainer(ContainerInterface $container)" method.
 */
class MigrationFactoryDecorator implements MigrationFactory
{
    private MigrationFactory   $migrationFactory;
    private ContainerInterface $container;

    public function __construct(MigrationFactory $migrationFactory, ContainerInterface $container)
    {
        $this->migrationFactory = $migrationFactory;
        $this->container        = $container;
    }

    public function createVersion(string $migrationClassName): AbstractMigration
    {
        $instance = $this->migrationFactory->createVersion($migrationClassName);

        // Symfony\Component\DependencyInjection\ContainerAwareInterface was removed in Symfony 7: "instanceof" was always false and
        // the migrations never received the container
        if (is_callable([$instance, 'setContainer'])) {
            $instance->setContainer($this->container);
        }

        return $instance;
    }
}
