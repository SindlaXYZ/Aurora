<?php

namespace Sindla\Bundle\AuroraBundle\Doctrine\DQL\PostgreSQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

/**
 * CASTASVARCHAR: Sindla\Bundle\AuroraBundle\Doctrine\DQL\PostgreSQL\CastAsVarchar
 *
 * Usage : CASTASVARCHAR(row.column)
 */
class CastAsVarchar extends FunctionNode
{
    private $string;

    public function getSql(SqlWalker $sqlWalker)
    {
        return 'CAST(' . $this->string->dispatch($sqlWalker) . " AS VARCHAR)";
    }

    public function parse(Parser $parser)
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);

        $this->string = $parser->StringPrimary();

        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }
}
