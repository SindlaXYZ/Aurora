<?php

namespace Sindla\Bundle\AuroraBundle\Doctrine\DQL;

use Doctrine\ORM\Query;
use Doctrine\ORM\Query\QueryException;

/**
 * The SortableNullsWalker is a TreeWalker that walks over a DQL AST and constructs
 * the corresponding SQL to allow ORDER BY x ASC NULLS FIRST|LAST.
 *
 * $qb = $em->createQueryBuilder()
 *     ->select('p')
 *     ->from('Webges\Domain\Core\Person\Person', 'p')
 *     ->where('p.id = 1')
 *     ->orderBy('p.firstname', 'ASC')
 *     ->addOrderBy('p.lastname', 'DESC')
 *     ->addOrderBy('p.id', 'DESC'); // relation to person
 *
 * $query = $qb->getQuery();
 * $query->setHint(Doctrine\ORM\Query::HINT_CUSTOM_OUTPUT_WALKER, 'Webges\DoctrineExtensions\Query\SortableNullsWalker');
 * $query->setHint("sortableNulls.fields", array(
 *     "p.firstname" => Webges\DoctrineExtensions\Query\SortableNullsWalker::NULLS_FIRST,
 *     "p.lastname"  => Webges\DoctrineExtensions\Query\SortableNullsWalker::NULLS_LAST,
 *     "p.id" => Webges\DoctrineExtensions\Query\SortableNullsWalker::NULLS_LAST
 * ));
 *
 * @see https://github.com/beberlei/DoctrineExtensions/blob/235b092e42697dcb3b2ebea3a85b79265d148a06/tests/Query/MysqlWalkerTest.php
 * @see https://www.doctrine-project.org/projects/doctrine-dbal/en/current/reference/query-builder.html#order-by-clause - is not working
 * @see http://www.doctrine-project.org/jira/browse/DDC-490
 */
class SortableNullsWalker extends Query\SqlWalker
{
    public const NULLS_FIRST = 'NULLS FIRST';

    public const NULLS_LAST = 'NULLS LAST';

    /**
     * @param $orderByItem
     * @return array|string
     * @throws QueryException
     */
    public function walkOrderByItem($orderByItem)
    {
        $sql  = parent::walkOrderByItem($orderByItem);
        $hint = $this->getQuery()->getHint('sortableNulls.fields');
        $expr = $orderByItem->expression;
        $type = strtoupper($orderByItem->type);

        if (is_array($hint) && count($hint)) {
            // check for a state field
            if (
                $expr instanceof Query\AST\PathExpression &&
                $expr->type == Query\AST\PathExpression::TYPE_STATE_FIELD
            ) {
                $fieldName = $expr->field;
                $dqlAlias  = $expr->identificationVariable;
                $search    = $this->walkPathExpression($expr) . ' ' . $type;
                $index     = $dqlAlias . '.' . $fieldName;
                if (isset($hint[$index])) {
                    $sql = str_replace($search, $search . ' ' . $hint[$index], $sql);
                }
            }
        }

        return $sql;
    }
}
