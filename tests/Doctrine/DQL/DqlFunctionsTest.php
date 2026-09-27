<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Doctrine\DQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Literal;
use Doctrine\ORM\Query\AST\PathExpression;
use Doctrine\ORM\Query\SqlWalker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Doctrine\DQL\MySQL\Month;
use Sindla\Bundle\AuroraBundle\Doctrine\DQL\MySQL\Timestamp;
use Sindla\Bundle\AuroraBundle\Doctrine\DQL\MySQL\Year;
use Sindla\Bundle\AuroraBundle\Doctrine\DQL\PostgreSQL\DateTrunc;

/**
 * clear; php vendor/phpunit/phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Doctrine/DQL/DqlFunctionsTest.php --no-coverage
 */
class DqlFunctionsTest extends TestCase
{
    /**
     * @param class-string<Month|Year|Timestamp> $functionClass
     */
    #[DataProvider('dataMySqlDateFunctions')]
    public function testMySqlDateFunctionsWalkTheParsedNode(string $functionClass, string $expectedSql): void
    {
        $pathExpression = new PathExpression(PathExpression::TYPE_STATE_FIELD, 'e', 'createdAt');

        // The parsed node used to be coerced to its debug dump by the "string" property type, then walked as a DQL alias
        $sqlWalker = $this->createMock(SqlWalker::class);
        $sqlWalker
            ->expects($this->once())
            ->method('walkArithmeticPrimary')
            ->with($this->identicalTo($pathExpression))
            ->willReturn('t0_.created_at');

        $function       = new $functionClass('F');
        $function->date = $pathExpression;

        $this->assertSame($expectedSql, $function->getSql($sqlWalker));
    }

    public static function dataMySqlDateFunctions(): array
    {
        return [
            [Month::class, 'MONTH(t0_.created_at)'],
            [Year::class, 'YEAR(t0_.created_at)'],
            [Timestamp::class, 'TIMESTAMP(t0_.created_at)'],
        ];
    }

    public function testPostgreSqlDateTrunc(): void
    {
        // Loading the class used to be a fatal error: parse() was not compatible with FunctionNode::parse(Parser $parser): void
        $function = new DateTrunc('DATE_TRUNC');
        $this->assertInstanceOf(FunctionNode::class, $function);

        $sqlWalker = $this->createStub(SqlWalker::class);
        $sqlWalker->method('walkLiteral')->willReturn("'month'");
        $sqlWalker->method('walkPathExpression')->willReturn('t0_.created_at');

        $function->firstDateExpression  = new Literal(Literal::STRING, 'month');
        $function->secondDateExpression = new PathExpression(PathExpression::TYPE_STATE_FIELD, 'e', 'createdAt');

        $this->assertSame("date_trunc('month', t0_.created_at)", $function->getSql($sqlWalker));
    }
}
