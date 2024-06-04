<?php

namespace Sindla\Bundle\AuroraBundle\Doctrine\DQL\PostgreSQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

/**
 * MINUTE: Sindla\Bundle\AuroraBundle\Doctrine\DQL\PostgreSQL\Minute
 *
 * Usage : MINUTE(row.column)
 */
class Minute extends FunctionNode
{
    private $date;

    public function getSql(SqlWalker $sqlWalker): string
    {
        return sprintf(
            'EXTRACT(MINUTE FROM %s)',
            $sqlWalker->walkArithmeticPrimary($this->date)
        );
    }

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->date = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }
}
