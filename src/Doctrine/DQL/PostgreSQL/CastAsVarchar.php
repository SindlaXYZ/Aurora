<?php

namespace Sindla\Bundle\AuroraBundle\Doctrine\DQL\PostgreSQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

/**
 * CASTASVARCHAR: Sindla\Bundle\AuroraBundle\Doctrine\DQL\PostgreSQL\CastAsVarchar
 *
 *  Usage: CASTASVARCHAR(row) will produce CAST(row AS VARCHAR)
 *  Eg:
 *       ->andWhere("CASTASVARCHAR({$tableName}.{$row}) ...");
 */
class CastAsVarchar extends FunctionNode
{
    private $string;

    public function getSql(SqlWalker $sqlWalker): string
    {
        return 'CAST(' . $this->string->dispatch($sqlWalker) . " AS VARCHAR)";
    }

    public function parse(Parser $parser): void
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        $this->string = $parser->StringPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }
}
