<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\EventSubscriber;

// Symfony
use Doctrine\Common\EventSubscriber;

// Doctrine
use Doctrine\DBAL\Schema\PostgreSqlSchemaManager;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

/**
 * doctrine:migrations:diff fix
 * No more useless CREATE SCHEMA public in down() migration
 *
 *  services.yaml:
 *
    Sindla\Bundle\AuroraBundle\\EventSubscriber\FixDefaultSchemaSubscriber:
        tags:
            - { name: doctrine.event_subscriber, connection: default }
 *
 * @package Sindla\Bundle\AuroraBundle\EventSubscriber
 */
class FixDefaultSchemaSubscriber implements EventSubscriber
{
    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents(): array
    {
        return [
            ToolEvents::postGenerateSchema,
        ];
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        $schemaManager = $args->getEntityManager()
            ->getConnection()
            ->getSchemaManager();

        if (!$schemaManager instanceof PostgreSqlSchemaManager) {
            return;
        }

        foreach ($schemaManager->getExistingSchemaSearchPaths() as $namespace) {
            if (!$args->getSchema()->hasNamespace($namespace)) {
                $args->getSchema()->createNamespace($namespace);
            }
        }
    }
}