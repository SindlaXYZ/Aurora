<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Schema\PostgreSqlSchemaManager;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;
use Doctrine\ORM\Events;

/**
 * doctrine:migrations:diff fix
 * No more useless CREATE SCHEMA public in down() migration
 *
 *  services.yaml:
 *
    Sindla\Bundle\AuroraBundle\EventSubscriber\FixDefaultSchemaSubscriber:
        tags:
            - { name: doctrine.event_subscriber, connection: default }
 *
 * @package Sindla\Bundle\AuroraBundle\EventSubscriber
 */
#[AsDoctrineListener(event: ToolEvents::postGenerateSchema)]
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
            ->createSchemaManager();

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
