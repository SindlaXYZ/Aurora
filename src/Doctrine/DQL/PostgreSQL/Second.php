<?php

namespace Sindla\Bundle\AuroraBundle\Doctrine\DQL\PostgreSQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

/**
 * SECOND: Sindla\Bundle\AuroraBundle\Doctrine\DQL\PostgreSQL\Second
 *
 * Usage : SECOND(row.column)
 */
class Second extends FunctionNode
{
    private $date;

    public function getSql(SqlWalker $sqlWalker): string
    {
        return sprintf(
            'FLOOR(EXTRACT(SECOND FROM %s))',
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
