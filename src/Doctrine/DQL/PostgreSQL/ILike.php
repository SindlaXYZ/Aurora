<?php

namespace Sindla\Bundle\AuroraBundle\Doctrine\DQL\PostgreSQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

/**
 * ILike: Sindla\Bundle\AuroraBundle\Doctrine\DQL\PostgreSQL\ILike
 *
 * Usage : ILike(row.column)
 */
class ILike extends FunctionNode
{
    private $string;
    private $query;

    public function getSql(SqlWalker $sqlWalker)
    {
        return '(' . $this->string->dispatch($sqlWalker) . ' ILIKE ' . $this->query->dispatch($sqlWalker) . ')';
    }

    public function parse(Parser $parser)
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);

        $this->field = $parser->StringExpression();

        $parser->match(Lexer::T_COMMA);

        $this->string = $parser->StringPrimary();

        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }
}
