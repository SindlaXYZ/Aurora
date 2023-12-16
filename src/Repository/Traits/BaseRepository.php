<?php

namespace Sindla\Bundle\AuroraBundle\Repository\Traits;

use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\QueryBuilder;
use Sindla\Bundle\AuroraBundle\Utils\Strink\Strink;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

trait BaseRepository
{
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
        $Strink    = new Strink();
        $className = $this->getClassName();
        $reflect   = new \ReflectionClass(new $className());

        $tableName = null;
        foreach ($reflect->getAttributes() as $attribute) {
            if (false) {
                echo "\n\n\n\n";
                print_r($attribute->getName());
                echo "\n\n";
                print_r($attribute->getArguments());
                echo "\n\n";
                print_r($attribute->newInstance());
                echo "\n\n\n\n";
            }
            if ($attribute->getName() == Table::class) {
                $tableName = str_replace('`', '', $attribute->getArguments()['name']);
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
         * $operation - AND | OR
         */
        foreach ($this->where as $operation => $conditions) {
            if ($conditions) {
                foreach ($conditions as $condition) {
                    [$column, $operator, $value] = $condition;

                    $jsonKey = null;
                    if (str_contains($column, '.')) {
                        [$column, $jsonKey] = explode('.', $column);
                    }

                    $ReflectionProperty = new \ReflectionProperty(new $className(), $column);

                    foreach ($ReflectionProperty->getAttributes() as $attribute) {
                        $randomKey = $Strink->randomString(6, ['ABCDEFGHIJKLMNOPQRSTUWXYZ']);

                        if (Column::class == $attribute->getName() && 'json' == $attribute->getArguments()['type']) {
                            if ('LIKE' == $operator) {
                                /**
                                 * Convert JSON to text and do a simple search LIKE; translates to SQL (eg):
                                 *  SELECT ... AND user.roles::text LIKE '%ROLE_SUPER_ADMIN%'
                                 */
                                $queryBuilder
                                    ->andWhere("JSON_TEXT({$tableName}.{$column}) {$operator} :{$randomKey}")
                                    ->setParameter($randomKey, $value);
                            } else {
                                /**
                                 * Search in JSON by the value of a specific key; for this example, the user.extraData = {"test":"abc"}; translates to SQL (eg):
                                 *  SELECT ... AND user.extraData ->> 'test' = 'abc'
                                 */
                                if (empty($jsonKey)) {
                                    throw new \Exception(sprintf('Invalid $column parameter: WHERE ... %1$s ->> \'\' %2$s \'%3$s\' ; This parameter should be like this: %1$s.jsonKey', $column, $operator, $value));
                                }
                                $queryBuilder
                                    ->andWhere("JSON_GET_TEXT({$tableName}.{$column}, '{$jsonKey}') {$operator} :{$randomKey}")
                                    ->setParameter($randomKey, $value);
                            }
                        } else {
                            $queryBuilder
                                ->andWhere("{$tableName}.{$column} {$operator} :{$randomKey}")
                                ->setParameter($randomKey, $value);
                        }
                    }
                }
            }
        }

        // ORDER BY
        if (0 != count($this->orders)) {
            foreach ($this->orders as $raw => $direction) {
                $queryBuilder->orderBy("{$tableName}.{$raw}", $direction);
            }
        }

        // LIMIT
        if ($this->limit) {
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
        $reflect        = new \ReflectionClass($this->getClassName());
        $namespaceParts = explode('\\', $reflect->getNamespaceName());

        $classMetaData = $this->_em->getClassMetadata("{$namespaceParts[0]}:{$reflect->getShortName()}");
        $connection    = $this->_em->getConnection();
        $dbPlatform    = $connection->getDatabasePlatform();
        $connection->beginTransaction();
        try {
            //$connection->query('SET FOREIGN_KEY_CHECKS=0');
            $q = $dbPlatform->getTruncateTableSql($classMetaData->getTableName());
            $connection->executeUpdate($q);
            //$connection->query('SET FOREIGN_KEY_CHECKS=1');
            $connection->commit();
        } catch (\Exception $e) {
            try {
                fwrite(STDERR, print_r('Can\'t truncate table ' . $classMetaData->getTableName() . '. Reason: ' . $e->getMessage(), true));
                $connection->rollback();
                return false;
            } catch (ConnectionException $connectionException) {
                fwrite(STDERR, print_r('Can\'t rollback truncating table ' . $classMetaData->getTableName() . '. Reason: ' . $connectionException->getMessage(), true));
                return false;
            }

            $connection->rollback();
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

            switch ($operation['operator']) {
                case 'LT' :
                case 'lt' :
                    $queryBuilder->andWhere("{$operation['alias']}.{$operation['field']} < :{$operation['field']}{$operation['operator']}");
                    $queryBuilder->setParameter("{$operation['field']}{$operation['operator']}", $operation['value']);
                    break;

                case 'GT' :
                case 'gt' :
                    $queryBuilder->andWhere("{$operation['alias']}.{$operation['field']} > :{$operation['field']}{$operation['operator']}");
                    $queryBuilder->setParameter("{$operation['field']}{$operation['operator']}", $operation['value']);
                    break;

                case 'LTE' :
                case 'lte' :
                    $queryBuilder->andWhere("{$operation['alias']}.{$operation['field']} <= :{$operation['field']}{$operation['operator']}");
                    $queryBuilder->setParameter("{$operation['field']}{$operation['operator']}", $operation['value']);
                    break;

                case 'GTE' :
                case 'gte' :
                    $queryBuilder->andWhere("{$operation['alias']}.{$operation['field']} >= :{$operation['field']}{$operation['operator']}");
                    $queryBuilder->setParameter("{$operation['field']}{$operation['operator']}", $operation['value']);
                    break;

                case 'EQ' :
                case 'eq' :
                case 'EXACT' :
                case 'exact' :
                    if ($operation['field'] == 'zoneId') {
                        $operation['field'] = 'parentId';
                        $operation['alias'] = 'zone';
                    }
                    $queryBuilder->andWhere("{$operation['alias']}.{$operation['field']} = :{$operation['field']}{$operation['operator']}");
                    $queryBuilder->setParameter("{$operation['field']}{$operation['operator']}", $operation['value']);
                    break;

                case 'IN' :
                case 'in' :
                    if ($operation['field'] == 'zone') {
                        $operation['field'] = 'parentId';
                        $operation['alias'] = 'zone';
                    }
                    $queryBuilder->andWhere("{$operation['alias']}.{$operation['field']} IN (:{$operation['field']}{$operation['operator']})");
                    $queryBuilder->setParameter("{$operation['field']}{$operation['operator']}", $operation['value']);
                    break;

                case 'RANGE' :
                case 'range' :
                    $orX = $queryBuilder->expr()->orX();
                    foreach ($operation['value'] as $operatorIndex => $operationValue) {
                        $andX = $queryBuilder->expr()->andX();
                        $andX->add(
                            $queryBuilder->expr()
                                ->gte(
                                    "{$operation['alias']}.{$operation['field']}",
                                    ":{$operation['field']}{$operation['operator']}{$operatorIndex}i0"
                                )
                        );
                        $andX->add(
                            $queryBuilder->expr()
                                ->lt(
                                    "{$operation['alias']}.{$operation['field']}",
                                    ":{$operation['field']}{$operation['operator']}{$operatorIndex}i1"
                                )
                        );
                        $orX->add(
                            $andX
                        );
                    }
                    $queryBuilder->andWhere($orX);
                    foreach ($operation['value'] as $operatorIndex => $operationValue) {
                        $queryBuilder->setParameter("{$operation['field']}{$operation['operator']}{$operatorIndex}i0", $operationValue[0]);
                        $queryBuilder->setParameter("{$operation['field']}{$operation['operator']}{$operatorIndex}i1", $operationValue[1]);
                    }
                    break;

                case 'LIKE' :
                case 'like' :
                    $queryBuilder->andWhere("{$operation['alias']}.{$operation['field']} LIKE :{$operation['field']}{$operation['operator']}");
                    $queryBuilder->setParameter("{$operation['field']}{$operation['operator']}", '%' . $operation['value'] . '%');
                    break;

                case 'ISNULL' :
                case 'isnull' :
                    $queryBuilder->andWhere("UNACCENT({$operation['alias']}.{$operation['field']}) IS NULL");
                    break;

                case 'ISNOTNULL' :
                case 'NOTNULL' :
                case 'isnotnull' :
                case 'notnull' :
                    $queryBuilder->andWhere("UNACCENT({$operation['alias']}.{$operation['field']}) IS NULL");
                    break;
            }
        }

        return $queryBuilder;
    }
}
