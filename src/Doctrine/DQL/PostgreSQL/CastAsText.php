<?php

namespace Sindla\Bundle\AuroraBundle\Doctrine\DQL\PostgreSQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * CASTASTEXT: Sindla\Bundle\AuroraBundle\Doctrine\DQL\PostgreSQL\CastAsText
 *
 *  Usage: CASTASTEXT(row) will produce CAST(row AS TEXT)
 *  Eg:
 *       ->andWhere("CASTASTEXT({$tableName}.{$row}) ...");
 */
class CastAsText extends FunctionNode
{
    private $string;

    public function getSql(SqlWalker $sqlWalker): string
    {
        return sprintf(
            'CAST(%s AS TEXT)',
            $sqlWalker->walkSimpleArithmeticExpression($this->string)
        );


        return 'CAST(' . $this->string->dispatch($sqlWalker) . " AS TEXT)";
    }

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->string = $parser->SimpleArithmeticExpression();

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }
}
