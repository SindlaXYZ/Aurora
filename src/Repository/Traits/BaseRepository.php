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

trait BaseRepository
{
    /** @var Container */
    protected Container $container;

    /** @var Request */
    protected Request $request;

    /** @var QueryBuilder $queryBuilder */
    protected QueryBuilder $queryBuilder;

    public function setContainer(Container $container)
    {
        $this->container = $container;
        return $this;
    }

    public function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

    protected ?array  $where       = [];
    protected ?int    $limit       = null;
    protected ?int    $limitOffset = null;
    protected bool    $count       = false;
    protected ?array  $orders      = [];

    public function setWhere(array $where)
    {
        $this->where = $where;
        return $this;
    }

    public function setOrder(array $orders)
    {
        $this->orders = $orders;
        return $this;
    }

    public function setLimit(int $limit = 1, int $offset = 1)
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
        $reflect        = new \ReflectionClass($this->getClassName());
        $namespaceParts = explode('\\', $reflect->getNamespaceName());

        $classMetaData = $this->_em->getClassMetadata("{$namespaceParts[0]}:{$reflect->getShortName()}");

        $AnnotationReader = new AnnotationReader();
        $classAnnotations = $AnnotationReader->getClassAnnotations($reflect);

        //print_r($reflect->name);die;

        $tableName = $classAnnotations[0]->name;

        $em = $this->getEntityManager();

        /** @var QueryBuilder $qb */
        $qb = $em->createQueryBuilder($tableName);

        if ($count) {
            $qb->select("count({$tableName}.id)");
        } else {
            $qb->select($tableName);
        }

        $qb->from($reflect->name, $tableName);

        //$qb = $this->createQueryBuilder('office')->addSelect('office');

        //print_r($x);die;
        //print_r($this->where);die;

        foreach ($this->where as $operation => $conditions) {
            if ($conditions) {
                foreach ($conditions as $condition) {
                    [$column, $operator, $value] = $condition;

                    $ReflectionProperty = new \ReflectionProperty($this->getClassName(), $column);
                    $ann                = $AnnotationReader->getPropertyAnnotations($ReflectionProperty);

                    //print_r($ann);die;
                    if (isset($ann[1]) && $ann[1] instanceof \Sindla\Bundle\AuroraBundle\Doctrine\Annotation\Aurora && true == boolval($ann[1]->bitwise)) {
                        $qb->andWhere("(BIT_AND({$tableName}.{$column}, {$value}) {$operator} " . $value . ")");
                    } else {
                        $qb->andWhere("{$tableName}.{$condition[0]} {$condition[1]} '{$condition[2]}'");
                    }
                }
            }
        }

        // Debug
        if ($getDQL) {
            return $qb->getDQL();
        }

        if ($this->limit) {
            $qb->setFirstResult($this->limitOffset);
            $qb->setMaxResults($this->limit);
        }

        if ($count) {
            return $qb->getQuery()->getSingleScalarResult();
        }

        if ($onlyOne) {
            return $qb->getQuery()->getOneOrNullResult();
        }

        return $qb->getQuery()->getResult();
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
     * @param array     $filters
     * @param Container $container
     * @return QueryBuilder
     */
    public function findAllQueryBuilder(array $filters = [], Container $container)
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