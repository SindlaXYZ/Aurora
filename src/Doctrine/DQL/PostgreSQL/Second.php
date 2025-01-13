<?php

namespace Sindla\Bundle\AuroraBundle\Doctrine\DQL\PostgreSQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * SECOND: Sindla\Bundle\AuroraBundle\Doctrine\DQL\PostgreSQL\Second
 *
 * Usage : SECOND(row.column)
 */
class Second extends FunctionNode
{
    private $second;

    public function getSql(SqlWalker $sqlWalker): string
    {
        return sprintf(
            'FLOOR(EXTRACT(SECOND FROM %s))',
            $sqlWalker->walkArithmeticPrimary($this->second)
        );
    }

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->second = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }
}
