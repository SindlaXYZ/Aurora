<?php

namespace Sindla\Bundle\AuroraBundle\Doctrine\DQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;

/**
 * Doctrine extension to support json_row::text
 * https://www.postgresql.org/docs/current/functions-json.html
 *
 * Install: Inside doctrine.yaml > doctrine > orm > dql > string_functions
 *
 * Usage: JSON_TEXT(row) will produce row::text
 * Eg:
 *      ->andWhere("JSON_TEXT({$tableName}.{$row}) LIKE ...");
 */
class JsonText extends FunctionNode
{
    private $string;

    public function getSql(\Doctrine\ORM\Query\SqlWalker $sqlWalker)
    {
        return $this->string->dispatch($sqlWalker) . "::text";
    }

    public function parse(\Doctrine\ORM\Query\Parser $parser)
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);

        $this->string = $parser->StringPrimary();

        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }
}
