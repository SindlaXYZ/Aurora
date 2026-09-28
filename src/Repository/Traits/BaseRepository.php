<?php

namespace Sindla\Bundle\AuroraBundle\Repository\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\QueryBuilder;
use Sindla\Bundle\AuroraBundle\Utils\AuroraStrink\AuroraStrink;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

trait BaseRepository
{
    /**
     * Operators accepted by setWhere(): the operator is written into the DQL, it must never be a free string
     */
    private const array WHERE_OPERATORS = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];

    protected ContainerInterface $container;

    protected Request $request;

    protected QueryBuilder $queryBuilder;

    public function setContainer(ContainerInterface $container): self
    {
        $this->container = $container;
        return $this;
    }

    public function setRequest(Request $request): self
    {
        $this->request = $request;
        return $this;
    }

    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

    protected ?array $where       = [];
    protected ?int   $limit       = null;
    protected ?int   $limitOffset = null;
    protected bool   $count       = false;
    protected ?array $orders      = [];

    public function setWhere(array $where): self
    {
        $this->where = $where;
        return $this;
    }

    public function setOrder(array $orders): self
    {
        $this->orders = $orders;
        return $this;
    }

    public function setLimit(int $limit = 1, int $offset = 0): self
    {
        $this->limit       = $limit;
        $this->limitOffset = $offset;
        return $this;
    }

    /**
     * Get one result
     *
     * @param bool $debug
     * @return
     * @throws \Exception
     */
    public function getResult(bool $debug = false)
    {
        return $this->extract(false, true, $debug);
    }

    /**
     * Get all results
     *
     * @param bool $debug
     * @return
     * @throws \Exception
     */
    public function getResults(bool $debug = false)
    {
        return $this->extract(false, false, $debug);
    }

    /**
     * Count and get all results
     *
     * @param bool $debug
     * @return
     * @throws \Exception
     */
    public function getResultsNumber(bool $debug = false)
    {
        $this->count = true;

        return $this->extract(true, null, $debug);
    }

    private function extract($count = false, $onlyOne = null, bool $getDQL = false)
    {
        $Strink        = new AuroraStrink();
        $className     = $this->getClassName();
        $reflect       = new \ReflectionClass($className); // @TODO: refactor this and use "symfony/property-info"
        $classMetadata = $this->getEntityManager()->getClassMetadata($className);

        $tableName = null;
        foreach ($reflect->getAttributes() as $attribute) {
            if ($attribute->getName() == Table::class) {
                $arguments = $attribute->getArguments();
                $tableName = str_replace('`', '', (string)($arguments['name'] ?? $arguments[0] ?? ''));
            }
        }

        if (!$tableName) {
            throw new \Exception('$tableName is not set!');
        }

        $em = $this->getEntityManager();

        $queryBuilder = $em->createQueryBuilder();

        if ($count) {
            $queryBuilder->select("count({$tableName}.id)");
        } else {
            $queryBuilder->select($tableName);
        }

        $queryBuilder->from($reflect->name, $tableName);

        /**
         * $operation - AND | OR: the conditions of an "OR" group are joined with OR, the groups are joined with AND
         *
         * The column and the operator are written into the DQL, so they are validated against the entity metadata / an allow-list
         * (they used to be concatenated as they were); the values are always bound as parameters.
         */
        foreach ($this->where as $operation => $conditions) {
            if (!$conditions) {
                continue;
            }

            $expressions = [];

            foreach ($conditions as $condition) {
                [$column, $operator, $value] = $condition;

                // "column.jsonKey", unless it is the field of an embeddable ("address.city")
                $jsonKey = null;
                if (str_contains($column, '.') && !$classMetadata->hasField($column)) {
                    [$column, $jsonKey] = explode('.', $column, 2);
                }

                if (!$classMetadata->hasField($column) && !$classMetadata->hasAssociation($column)) {
                    throw new \InvalidArgumentException(sprintf('Invalid column "%s": it is not a field of the "%s" entity.', $column, $className));
                }

                $operator = strtoupper(trim(preg_replace('/\s+/', ' ', (string)$operator)));

                if (!in_array($operator, self::WHERE_OPERATORS, true)) {
                    throw new \InvalidArgumentException(sprintf('Invalid operator "%s", use one of: "%s".', $operator, implode('", "', self::WHERE_OPERATORS)));
                }

                $randomKey   = $Strink->randomString(6, ['ABCDEFGHIJKLMNOPQRSTUWXYZ']);
                $placeholder = in_array($operator, ['IN', 'NOT IN'], true) ? "(:{$randomKey})" : ":{$randomKey}";

                // The column type is read from the Doctrine metadata: the condition used to be added once per PHP attribute of the property
                // (never for a property without attributes), and "#[ORM\Column]" without an explicit "type" was an "Undefined array key" error
                if ($classMetadata->hasField($column) && Types::JSON === $classMetadata->getTypeOfField($column)) {
                    if ('LIKE' == $operator) {
                        /**
                         * Convert JSON to text and do a simple search LIKE; translates to SQL (eg):
                         *  SELECT ... AND user.roles::text LIKE '%ROLE_SUPER_ADMIN%'
                         */
                        $expressions[] = "JSON_TEXT({$tableName}.{$column}) {$operator} {$placeholder}";
                    } else {
                        /**
                         * Search in JSON by the value of a specific key; for this example, the user.extraData = {"test":"abc"}; translates to SQL (eg):
                         *  SELECT ... AND user.extraData ->> 'test' = 'abc'
                         */
                        if (empty($jsonKey)) {
                            throw new \Exception(sprintf(
                                'Invalid $column parameter: WHERE ... %1$s ->> \'\' %2$s \'%3$s\' ; This parameter should be like this: %1$s.jsonKey',
                                $column,
                                $operator,
                                $value
                            ));
                        }

                        // DQL string literal: a single quote is escaped by doubling it
                        $jsonKey       = str_replace("'", "''", $jsonKey);
                        $expressions[] = "JSON_GET_TEXT({$tableName}.{$column}, '{$jsonKey}') {$operator} {$placeholder}";
                    }
                } else if (null === $value && in_array($operator, ['=', '!=', '<>'], true)) {
                    $expressions[] = "{$tableName}.{$column} IS " . ('=' === $operator ? 'NULL' : 'NOT NULL');

                    continue;
                } else {
                    $expressions[] = "{$tableName}.{$column} {$operator} {$placeholder}";
                }

                $queryBuilder->setParameter($randomKey, $value);
            }

            if ('OR' === strtoupper((string)$operation) && count($expressions) > 1) {
                $queryBuilder->andWhere($queryBuilder->expr()->orX(...$expressions));
            } else {
                foreach ($expressions as $expression) {
                    $queryBuilder->andWhere($expression);
                }
            }
        }

        // ORDER BY (every column, not only the last one: orderBy() replaces the previous ORDER BY)
        // A COUNT() query is neither ordered nor paginated: "ORDER BY" fails on PostgreSQL, an offset skips the only row
        foreach ($count ? [] : $this->orders as $raw => $direction) {
            if (!$classMetadata->hasField($raw)) {
                throw new \InvalidArgumentException(sprintf('Invalid order column "%s": it is not a field of the "%s" entity.', $raw, $className));
            }

            $direction = strtoupper(trim((string)$direction));

            if (!in_array($direction, ['ASC', 'DESC'], true)) {
                throw new \InvalidArgumentException(sprintf('Invalid order direction "%s", use "ASC" or "DESC".', $direction));
            }

            $queryBuilder->addOrderBy("{$tableName}.{$raw}", $direction);
        }

        // LIMIT
        if ($this->limit && !$count) {
            $queryBuilder->setFirstResult($this->limitOffset);
            $queryBuilder->setMaxResults($this->limit);
        }

        // Debug
        if ($getDQL) {
            $query = $queryBuilder->getQuery();

            echo "\n--------------------------------------------------------------\n";

            echo "\n[DQL]\n";
            echo $queryBuilder->getDQL() . "\n\n";

            echo "\n[SQL]\n";
            echo $query->getSql() . "\n\n";

            $queryParams = $query->getParameters();
            if (!empty($queryParams)) {
                echo "\n[PARAMS]\n";
                print_r($queryParams);
            }

            echo "\n--------------------------------------------------------------\n";

            exit(0);
        }

        if ($count) {
            return $queryBuilder->getQuery()->getSingleScalarResult();
        }

        if ($onlyOne) {
            return $queryBuilder->getQuery()->getOneOrNullResult();
        }

        return $queryBuilder->getQuery()->getResult();
    }

    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

    public function truncate(): bool
    {
        // The removed "_em" property, "App:Entity" aliases and Connection::executeUpdate() made this method a fatal error on ORM 3 / DBAL 4
        $em            = $this->getEntityManager();
        $classMetaData = $em->getClassMetadata($this->getClassName());
        $connection    = $em->getConnection();
        $dbPlatform    = $connection->getDatabasePlatform();
        $tableName     = $em->getConfiguration()->getQuoteStrategy()->getTableName($classMetaData, $dbPlatform);

        try {
            // A single statement (TRUNCATE is not transactional on MySQL: it commits implicitly)
            $connection->executeStatement($dbPlatform->getTruncateTableSQL($tableName));
        } catch (\Exception $e) {
            // STDERR is only defined by the CLI SAPI
            if (\defined('STDERR')) {
                fwrite(STDERR, sprintf('Can\'t truncate table %s. Reason: %s', $tableName, $e->getMessage()));
            }

            return false;
        }

        return true;
    }

    /**
     * @param array $filters
     * @return QueryBuilder
     */
    public function findAllQueryBuilder(array $filters = []): QueryBuilder
    {
        if (count($filters) == 0) {
            $queryBuilder = $this->createQueryBuilder('alias');
        } else {
            $queryBuilder = $this->applyFilters($this->createQueryBuilder('alias'), $filters);
        }

        return $queryBuilder;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param              $operations
     * @return QueryBuilder
     */
    protected function applyFilters(QueryBuilder $queryBuilder, $operations): QueryBuilder
    {
        foreach ($operations as $operation) {

            if (!isset($operation['alias'])) {
                $operation['alias'] = $queryBuilder->getRootAliases()[0];
            }

            // The alias and the field are written into the DQL (and into the parameter names): identifiers only
            foreach (['alias', 'field'] as $identifier) {
                $value = $operation[$identifier] ?? null;

                if (!is_string($value) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
                    throw new \InvalidArgumentException(sprintf('Invalid filter %s "%s".', $identifier, is_string($value) ? $value : get_debug_type($value)));
                }
            }

            // Case-insensitive ("Gt" used to be ignored); an unknown operator used to be ignored as well, silently: the filter was dropped
            // and every row was returned (e.g. a filter by owner or tenant)
            $operator = is_string($operation['operator'] ?? null) ? strtoupper(trim($operation['operator'])) : '';
            $field    = "{$operation['alias']}.{$operation['field']}";

            // Unique: two filters on the same field and operator ("price GT 5" and "price GT 25") used to share one parameter, the value
            // of the last filter was used for both
            $parameter = $operation['field'] . $operator . $queryBuilder->getParameters()->count();

            switch ($operator) {
                case 'LT' :
                    $queryBuilder->andWhere("{$field} < :{$parameter}");
                    $queryBuilder->setParameter($parameter, $operation['value']);
                    break;

                case 'GT' :
                    $queryBuilder->andWhere("{$field} > :{$parameter}");
                    $queryBuilder->setParameter($parameter, $operation['value']);
                    break;

                case 'LTE' :
                    $queryBuilder->andWhere("{$field} <= :{$parameter}");
                    $queryBuilder->setParameter($parameter, $operation['value']);
                    break;

                case 'GTE' :
                    $queryBuilder->andWhere("{$field} >= :{$parameter}");
                    $queryBuilder->setParameter($parameter, $operation['value']);
                    break;

                case 'EQ' :
                case 'EXACT' :
                    // Only when the query joins the "zone" alias: without it, "zoneId" was an invalid query ("zone" is not defined)
                    if ('zoneId' == $operation['field'] && in_array('zone', $queryBuilder->getAllAliases(), true)) {
                        $field = 'zone.parentId';
                    }
                    $queryBuilder->andWhere("{$field} = :{$parameter}");
                    $queryBuilder->setParameter($parameter, $operation['value']);
                    break;

                case 'IN' :
                    if ('zone' == $operation['field'] && in_array('zone', $queryBuilder->getAllAliases(), true)) {
                        $field = 'zone.parentId';
                    }
                    $queryBuilder->andWhere("{$field} IN (:{$parameter})");
                    $queryBuilder->setParameter($parameter, $operation['value']);
                    break;

                case 'RANGE' :
                    // [[from, to], ...]: the keys of the list were written into the DQL (in the parameter names)
                    if (!is_array($operation['value']) || [] === $operation['value']) {
                        throw new \InvalidArgumentException(sprintf('Invalid filter value for the "RANGE" operator of "%s": a list of [from, to] pairs is expected.', $operation['field']));
                    }

                    $orX = $queryBuilder->expr()->orX();
                    foreach (array_values($operation['value']) as $rangeIndex => $range) {
                        if (!is_array($range) || 2 !== count($range)) {
                            throw new \InvalidArgumentException(sprintf('Invalid filter value for the "RANGE" operator of "%s": a list of [from, to] pairs is expected.', $operation['field']));
                        }

                        [$from, $to] = array_values($range);
                        $orX->add($queryBuilder->expr()->andX(
                            $queryBuilder->expr()->gte($field, ":{$parameter}i{$rangeIndex}from"),
                            $queryBuilder->expr()->lt($field, ":{$parameter}i{$rangeIndex}to")
                        ));
                        $queryBuilder->setParameter("{$parameter}i{$rangeIndex}from", $from);
                        $queryBuilder->setParameter("{$parameter}i{$rangeIndex}to", $to);
                    }
                    $queryBuilder->andWhere($orX);
                    break;

                case 'LIKE' :
                    $queryBuilder->andWhere("{$field} LIKE :{$parameter}");
                    $queryBuilder->setParameter($parameter, '%' . $operation['value'] . '%');
                    break;

                // Without UNACCENT(): a PostgreSQL function (a MySQL query failed), only for text columns
                case 'ISNULL' :
                    $queryBuilder->andWhere("{$field} IS NULL");
                    break;

                case 'ISNOTNULL' :
                case 'NOTNULL' :
                    $queryBuilder->andWhere("{$field} IS NOT NULL");
                    break;

                default:
                    $invalidOperator = $operation['operator'] ?? null;

                    throw new \InvalidArgumentException(sprintf('Invalid filter operator "%s".', is_scalar($invalidOperator) ? $invalidOperator : get_debug_type($invalidOperator)));
            }
        }

        return $queryBuilder;
    }
}
