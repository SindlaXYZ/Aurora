<?php

namespace Sindla\Bundle\AuroraBundle\Repository\Traits;

use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\Container;

trait BaseRepository
{
    /** @var Container */
    protected $container;

    public function setContainer(Container $container)
    {
        $this->container = $container;
        return $this;
    }

    /** @var Request */
    protected $request;

    public function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    public function truncate()
    {
        $reflect = new \ReflectionClass($this->getClassName());
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