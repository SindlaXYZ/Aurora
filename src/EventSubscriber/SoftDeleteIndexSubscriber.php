<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Sindla\Bundle\AuroraBundle\Entity\SuperClass\AbstractTimestampableDeleted;

/**
 * Automatically adds a unique soft delete index (idx_{table}_deleted_at) to all entities
 * that extend AbstractTimestampableDeleted.
 *
 * This solves the PostgreSQL limitation where index names must be unique across the entire database,
 * not just within a single table.
 */
#[AsDoctrineListener(event: Events::loadClassMetadata)]
class SoftDeleteIndexSubscriber
{
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $metadata = $eventArgs->getClassMetadata();

        // Skip if not a concrete entity (abstract classes, mapped superclasses)
        if ($metadata->isMappedSuperclass || $metadata->isEmbeddedClass) {
            return;
        }

        // Check if the entity extends AbstractTimestampableDeleted
        if (!is_subclass_of($metadata->getName(), AbstractTimestampableDeleted::class)) {
            return;
        }

        // Check if the deleted_at column exists
        if (!isset($metadata->fieldMappings['deletedAt'])) {
            return;
        }

        $tableName = $metadata->getTableName();
        $indexName = 'idx_' . $tableName . '_soft_delete';

        // Check if an index for deleted_at already exists (user might have defined one manually)
        foreach ($metadata->table['indexes'] ?? [] as $existingIndex) {
            if (isset($existingIndex['columns']) && $existingIndex['columns'] === ['deleted_at']) {
                return; // Index already defined, skip
            }
        }

        // Add the unique index
        $metadata->table['indexes'][$indexName] = [
            'columns' => ['deleted_at'],
        ];
    }
}
