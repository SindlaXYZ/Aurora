<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\FieldMapping;
use Doctrine\ORM\Mapping\ManyToManyOwningSideMapping;
use Doctrine\ORM\Mapping\OneToOneOwningSideMapping;
use Doctrine\ORM\Mapping\ToOneOwningSideMapping;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Exports the Doctrine entity schema (tables, columns, FK relations, indexes) as schema.json + schema.js for the static ERD viewer in src/Resources/public/db-diagram/diagram.html.
 * The schema is read from Doctrine ClassMetadata (NOT from the live database), so it reflects exactly what the entities in src/Entity/ declare - inheritance, naming strategy, and real join
 * column names included - and it works even when the database is unreachable. Tables with no entity (e.g. the *_audit tables, doctrine_migration_versions) are intentionally out of scope.
 *
 * Regenerate with: /usr/bin/php bin/console app:db-diagram
 * Then open src/Resources/public/db-diagram/diagram.html directly in a browser (file:// - no webserver needed; the data travels via schema.js because fetch() is CORS-blocked under file://).
 *
 * See .claude/plans/2026-06-05-db-diagram-generator.md for the full contract between this command and the viewer.
 */
#[AsCommand(
    name       : 'app:db-diagram',
    description: 'Export the Doctrine entity schema as schema.json + schema.js for the static ERD viewer (src/Resources/public/db-diagram/diagram.html).'
)]
final readonly class DbDiagramCommand
{
    public function __construct(
        private EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')]
        private string                 $projectDir,
    )
    {
    }

    /*
     * NOTE: a `SymfonyStyle $io` parameter must NOT be used here. This project registers SymfonyStyle as a container service (Aurora SymfonyStyleFactory), so the console ServiceValueResolver
     * yields it for the parameter and shifts every following resolved argument by one position (TypeError: $outputDir receives the SymfonyStyle). InputInterface / OutputInterface are not
     * container services, so they resolve cleanly through the invokable-command core-utility injection; the SymfonyStyle is built from them inside the method instead.
     */
    public function __invoke(
        InputInterface  $input,
        OutputInterface $output,
        // The parameter cannot be named $output: the console argument resolver reserves that name for the OutputInterface-style injections, so the CLI name is pinned via the attribute.
        #[Option(description: 'Target directory for schema.json + schema.js (relative to the project root, or absolute).', name: 'output')]
        string          $outputDir = 'src/Resources/public/db-diagram',
        #[Option(description: 'Pretty-print the JSON (diff-friendly). Use --no-pretty for the compact form.')]
        bool            $pretty = true,
    ): int
    {
        $io        = new SymfonyStyle($input, $output);
        $outputDir = str_starts_with($outputDir, '/') ? $outputDir : $this->projectDir . '/' . rtrim($outputDir, '/');

        if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
            $io->error(sprintf('Cannot create the output directory "%s".', $outputDir));

            return Command::FAILURE;
        }

        $schema = $this->buildSchema();

        $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
        if (true === $pretty) {
            $jsonFlags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($schema, $jsonFlags);

        file_put_contents($outputDir . '/schema.json', $json . "\n");
        // The same payload wrapped as a JS global: <script src="schema.js"> works under file:// in every browser, while fetch('schema.json') is CORS-blocked in Chrome/Edge.
        file_put_contents($outputDir . '/schema.js', 'window.DB_SCHEMA = ' . $json . ";\n");

        $io->success(sprintf(
            'Exported %d tables and %d relations to %s/{schema.json,schema.js}',
            \count($schema['tables']),
            \count($schema['relations']),
            $outputDir
        ));

        return Command::SUCCESS;
    }

    /**
     * Builds the full schema payload from the Doctrine metadata of every concrete entity. The shape is the contract consumed by src/Resources/public/db-diagram/diagram.html: the viewer reads
     * tables[].{name,columns[].{name,type,pk,fk,nullable}} and relations[].{fromTable,fromColumn,toTable,toColumn}; every other key (entityClass, isJoinTable, indexes, type, field,
     * onDelete, generatedAt, platform) is informational and must be tolerated/ignored by consumers.
     *
     * @return array{generatedAt: string, platform: string, tables: list<array<string, mixed>>, relations: list<array<string, mixed>>}
     */
    private function buildSchema(): array
    {
        $tables    = [];
        $relations = [];

        foreach ($this->em->getMetadataFactory()->getAllMetadata() as $metadata) {
            // Mapped superclasses have no table of their own; their fields reappear in each concrete child's metadata.
            if ($metadata->isMappedSuperclass) {
                continue;
            }

            $tableName = $this->unquote($metadata->getTableName());
            $columns   = [];

            foreach ($metadata->fieldMappings as $fieldName => $mapping) {
                $columns[] = [
                    'name'     => $this->unquote($mapping->columnName),
                    'type'     => $this->renderFieldType($mapping),
                    'pk'       => \in_array($fieldName, $metadata->identifier, true),
                    'fk'       => false,
                    'nullable' => true === $mapping->nullable,
                ];
            }

            foreach ($metadata->associationMappings as $association) {
                // Only the owning side carries the join column / join table definition; the inverse side would duplicate every edge.
                if (!$association->isOwningSide()) {
                    continue;
                }

                $targetMetadata  = $this->em->getClassMetadata($association->targetEntity);
                $targetTableName = $this->unquote($targetMetadata->getTableName());

                if ($association instanceof ToOneOwningSideMapping) {
                    $relationType = $association instanceof OneToOneOwningSideMapping ? 'one-to-one' : 'many-to-one';

                    foreach ($association->joinColumns as $joinColumn) {
                        $columnName       = $this->unquote($joinColumn->name);
                        $referencedColumn = $this->unquote($joinColumn->referencedColumnName);

                        $columns[] = [
                            'name'     => $columnName,
                            'type'     => $this->renderReferencedColumnType($targetMetadata, $referencedColumn),
                            'pk'       => true === $association->id,
                            'fk'       => true,
                            'nullable' => true === $joinColumn->nullable,
                        ];

                        $relations[] = [
                            'fromTable'  => $tableName,
                            'fromColumn' => $columnName,
                            'toTable'    => $targetTableName,
                            'toColumn'   => $referencedColumn,
                            'type'       => $relationType,
                            'field'      => $association->fieldName,
                            'onDelete'   => $joinColumn->onDelete,
                        ];
                    }
                }

                if ($association instanceof ManyToManyOwningSideMapping) {
                    // The association table is not an entity, so it is synthesized here as its own node: composite-PK FK columns plus one edge towards each side of the relation.
                    $joinTableName    = $this->unquote($association->joinTable->name);
                    $joinTableColumns = [];

                    foreach ($association->joinTable->joinColumns as $joinColumn) {
                        $columnName       = $this->unquote($joinColumn->name);
                        $referencedColumn = $this->unquote($joinColumn->referencedColumnName);

                        $joinTableColumns[] = [
                            'name'     => $columnName,
                            'type'     => $this->renderReferencedColumnType($metadata, $referencedColumn),
                            'pk'       => true,
                            'fk'       => true,
                            'nullable' => false,
                        ];

                        $relations[] = [
                            'fromTable'  => $joinTableName,
                            'fromColumn' => $columnName,
                            'toTable'    => $tableName,
                            'toColumn'   => $referencedColumn,
                            'type'       => 'many-to-many',
                            'field'      => $association->fieldName,
                            'onDelete'   => $joinColumn->onDelete,
                        ];
                    }

                    foreach ($association->joinTable->inverseJoinColumns as $joinColumn) {
                        $columnName       = $this->unquote($joinColumn->name);
                        $referencedColumn = $this->unquote($joinColumn->referencedColumnName);

                        $joinTableColumns[] = [
                            'name'     => $columnName,
                            'type'     => $this->renderReferencedColumnType($targetMetadata, $referencedColumn),
                            'pk'       => true,
                            'fk'       => true,
                            'nullable' => false,
                        ];

                        $relations[] = [
                            'fromTable'  => $joinTableName,
                            'fromColumn' => $columnName,
                            'toTable'    => $targetTableName,
                            'toColumn'   => $referencedColumn,
                            'type'       => 'many-to-many',
                            'field'      => $association->fieldName,
                            'onDelete'   => $joinColumn->onDelete,
                        ];
                    }

                    $tables[] = [
                        'name'        => $joinTableName,
                        'entityClass' => null,
                        'isJoinTable' => true,
                        'columns'     => $joinTableColumns,
                        'indexes'     => [],
                    ];
                }
            }

            $tables[] = [
                'name'        => $tableName,
                'entityClass' => $metadata->getName(),
                'isJoinTable' => false,
                'columns'     => $columns,
                'indexes'     => $this->extractIndexes($metadata),
            ];
        }

        // Deterministic, diff-friendly output: tables alphabetically, relations by their source endpoint.
        usort($tables, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
        usort($relations, static fn(array $a, array $b): int => [$a['fromTable'], $a['fromColumn']] <=> [$b['fromTable'], $b['fromColumn']]);

        return [
            'generatedAt' => date('c'),
            'platform'    => $this->resolvePlatform(),
            'tables'      => $tables,
            'relations'   => $relations,
        ];
    }

    /**
     * Renders a field's Doctrine DBAL type as a short SQL-ish label (the project runs on PostgreSQL): string -> varchar(N), decimal -> numeric(P,S), json -> jsonb, etc.
     * Unknown / custom types (date_point, ...) pass through verbatim - the viewer displays the label as-is.
     */
    private function renderFieldType(FieldMapping $mapping): string
    {
        static $map = [
            'string'               => 'varchar',
            'ascii_string'         => 'varchar',
            'text'                 => 'text',
            'integer'              => 'int',
            'smallint'             => 'smallint',
            'bigint'               => 'bigint',
            'boolean'              => 'bool',
            'decimal'              => 'numeric',
            'float'                => 'float',
            'json'                 => 'jsonb',
            'guid'                 => 'uuid',
            'uuid'                 => 'uuid',
            'datetime'             => 'timestamp',
            'datetime_immutable'   => 'timestamp',
            'datetimetz'           => 'timestamptz',
            'datetimetz_immutable' => 'timestamptz',
            'date_point'           => 'timestamp',
            'date'                 => 'date',
            'date_immutable'       => 'date',
            'day_point'            => 'date',
            'time'                 => 'time',
            'time_immutable'       => 'time',
            'time_point'           => 'time',
            'blob'                 => 'bytea',
            'binary'               => 'bytea',
        ];

        $label = $map[$mapping->type] ?? $mapping->type;

        if ('numeric' === $label) {
            return sprintf('numeric(%d,%d)', $mapping->precision ?? 10, $mapping->scale ?? 0);
        }

        if ('varchar' === $label && null !== $mapping->length) {
            return sprintf('varchar(%d)', $mapping->length);
        }

        return $label;
    }

    /**
     * Resolves the rendered type of an FK column from the column it references on the target entity (an FK column has the same SQL type as the PK it points to).
     */
    private function renderReferencedColumnType(ClassMetadata $targetMetadata, string $referencedColumnName): string
    {
        $fieldName = $targetMetadata->fieldNames[$referencedColumnName] ?? null;

        if (null === $fieldName || !isset($targetMetadata->fieldMappings[$fieldName])) {
            return 'int';
        }

        return $this->renderFieldType($targetMetadata->fieldMappings[$fieldName]);
    }

    /**
     * Extracts the #[ORM\Index] / #[ORM\UniqueConstraint] declarations from the table mapping. Partial-index options (e.g. "where") are not exported - only name, columns, and uniqueness.
     *
     * @param ClassMetadata<object> $metadata
     *
     * @return list<array{name: string, columns: list<string>, unique: bool}>
     */
    private function extractIndexes(ClassMetadata $metadata): array
    {
        $indexes = [];

        foreach ($metadata->table['indexes'] ?? [] as $name => $index) {
            $indexes[] = [
                'name'    => (string)$name,
                'columns' => array_values($index['columns'] ?? []),
                'unique'  => \in_array('unique', $index['flags'] ?? [], true),
            ];
        }

        foreach ($metadata->table['uniqueConstraints'] ?? [] as $name => $constraint) {
            $indexes[] = [
                'name'    => (string)$name,
                'columns' => array_values($constraint['columns'] ?? []),
                'unique'  => true,
            ];
        }

        return $indexes;
    }

    /**
     * Maps the DBAL driver name to a human-readable platform label without touching the database (getDatabasePlatform() may open a connection, and this command must work offline).
     */
    private function resolvePlatform(): string
    {
        $driver = (string)($this->em->getConnection()->getParams()['driver'] ?? '');

        return match ($driver) {
            'pdo_pgsql', 'pgsql'    => 'postgresql',
            'pdo_mysql', 'mysqli'   => 'mysql',
            'pdo_sqlite', 'sqlite3' => 'sqlite',
            default                 => '' === $driver ? 'unknown' : $driver,
        };
    }

    /**
     * Strips the Doctrine identifier-quoting characters (backticks / double quotes) - e.g. the User entity declares its table name quoted as `users`.
     */
    private function unquote(string $name): string
    {
        return trim($name, '`"');
    }
}
