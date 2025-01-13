<?php

namespace Sindla\Bundle\AuroraBundle\Doctrine\DQL\PostgreSQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * DATE_TRUNC: Sindla\Bundle\AuroraBundle\Doctrine\DQL\PostgreSQL\DateTrunc
 *
 * Usage : DATE_TRUNC('month', row.column)
 */
class DateTrunc extends FunctionNode
{
    public $firstDateExpression  = null;
    public $secondDateExpression = null;

    public function getSql(SqlWalker $sqlWalker): string
    {
        return 'date_trunc('
            . $this->firstDateExpression->dispatch($sqlWalker)
            . ', '
            . $this->secondDateExpression->dispatch($sqlWalker)
            . ')';
    }

    public function parse(Parser $parser)
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->firstDateExpression = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->secondDateExpression = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }
}
