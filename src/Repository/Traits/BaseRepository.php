<?php

namespace Sindla\Bundle\AuroraBundle\Repository\Traits;

// Doctrine
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Common\Annotations\AnnotationReader;

//  Symfony
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\Container;

// Aurora
use Sindla\Bundle\AuroraBundle\Utils\Strink\Strink;

trait BaseRepository
{
    /** @var Container */
    protected Container $container;

    /** @var Request */
    protected Request $request;

    /** @var QueryBuilder $queryBuilder */
    protected QueryBuilder $queryBuilder;

    public function setContainer(Container $container): self
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

    public function setLimit(int $limit = 1, int $offset = 1): self
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
     */
    public function getResultsNumber(bool $debug = false)
    {
        $this->count = true;

        return $this->extract(true, null, $debug);
    }

    private function extract($count = false, $onlyOne = null, bool $getDQL = false)
    {
        $Strink         = new Strink();
        $reflect        = new \ReflectionClass($this->getClassName());

        $AnnotationReader = new AnnotationReader();
        $classAnnotations = $AnnotationReader->getClassAnnotations($reflect);

        $tableName = str_replace('`', '', $classAnnotations[0]->name);

        $em = $this->getEntityManager();

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $em->createQueryBuilder($tableName);

        if ($count) {
            $queryBuilder->select("count({$tableName}.id)");
        } else {
            $queryBuilder->select($tableName);
        }

        $queryBuilder->from($reflect->name, $tableName);

        foreach ($this->where as $operation => $conditions) {
            if ($conditions) {
                foreach ($conditions as $condition) {
                    [$column, $operator, $value] = $condition;

                    $ReflectionProperty = new \ReflectionProperty($this->getClassName(), $column);
                    $ann                = $AnnotationReader->getPropertyAnnotations($ReflectionProperty);
                    $randomKey          = $Strink->randomString(6, ['ABCDEFGHIJKLMNOPQRSTUWXYZ']);

                    // Aurora column
                    if (isset($ann[1]) && $ann[1] instanceof \Sindla\Bundle\AuroraBundle\Doctrine\Annotation\Aurora) {
                        // Bitwise
                        if (true == boolval($ann[1]->bitwise)) {
                            // ->andWhere('BIT_AND(t.my_column, 2|4|8|16..) > 0')
                            $queryBuilder->andWhere("(BIT_AND({$tableName}.{$column}, {$value}) {$operator} " . $value . ")");
                        } // Json + LIKE
                        else if (true == boolval($ann[1]->json) && 'LIKE' == $condition[1]) {
                            $queryBuilder->andWhere("JSON_TEXT({$tableName}.{$condition[0]}) {$condition[1]} :{$randomKey}");
                            $queryBuilder->setParameter($randomKey, $condition[2]);

                        } // Json
                        else if (true == boolval($ann[1]->json)) {
                            $queryBuilder->andWhere("JSON_GET_TEXT({$tableName}.{$condition[0]}, '') {$condition[1]} :{$randomKey}");
                            $queryBuilder->setParameter($randomKey, $condition[2]);
                        }

                    } else {
                        $queryBuilder->andWhere("{$tableName}.{$condition[0]} {$condition[1]} :{$randomKey}");
                        $queryBuilder->setParameter($randomKey, $condition[2]);
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

    public function truncate()
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
    }

    /**
     * @param array $filters
     * @return QueryBuilder
     */
    public function findAllQueryBuilder(array $filters = [])
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
    protected function applyFilters(QueryBuilder $queryBuilder, $operations)
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
