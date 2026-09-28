<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Doctrine\DQL;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Literal;
use Doctrine\ORM\Query\AST\PathExpression;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Query\SqlWalker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Doctrine\DQL\MySQL\Month;
use Sindla\Bundle\AuroraBundle\Doctrine\DQL\MySQL\Timestamp;
use Sindla\Bundle\AuroraBundle\Doctrine\DQL\MySQL\Year;
use Sindla\Bundle\AuroraBundle\Doctrine\DQL\PostgreSQL;
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

    /**
     * @param array<string, class-string<FunctionNode>> $functions
     */
    #[DataProvider('dataParsedFunctions')]
    public function testTheParsedFunctionIsWalkedIntoSql(string $platform, array $functions, string $dqlExpression, string $expectedSql): void
    {
        $em = $this->createEntityManager($platform, $functions);

        $sql = $em->createQuery(sprintf('SELECT %s FROM %s e', $dqlExpression, DqlFunctionsEvent::class))->getSQL();

        $this->assertSame(sprintf('SELECT %s AS sclr_0 FROM dql_functions_event d0_', $expectedSql), $sql);
        // Only the platform is needed: nothing connects to a database server
        $this->assertFalse($em->getConnection()->isConnected());
    }

    public static function dataParsedFunctions(): iterable
    {
        yield 'MySQL MONTH' => ['mysql', ['MONTH' => Month::class], 'MONTH(e.createdAt)', 'MONTH(d0_.created_at)'];
        yield 'MySQL YEAR of a parameter' => ['mysql', ['YEAR' => Year::class], 'YEAR(:date)', 'YEAR(?)'];
        yield 'MySQL TIMESTAMP' => ['mysql', ['TIMESTAMP' => Timestamp::class], 'TIMESTAMP(e.createdAt)', 'TIMESTAMP(d0_.created_at)'];
        yield 'CASTASTEXT of an arithmetic expression' => ['postgresql', ['CASTASTEXT' => PostgreSQL\CastAsText::class], 'CASTASTEXT(e.id + 1)', 'CAST(d0_.id + 1 AS TEXT)'];
        yield 'CASTASVARCHAR' => ['postgresql', ['CASTASVARCHAR' => PostgreSQL\CastAsVarchar::class], 'CASTASVARCHAR(e.name)', 'CAST(d0_.name AS VARCHAR)'];
        yield 'DATE_TRUNC' => ['postgresql', ['DATE_TRUNC' => DateTrunc::class], 'DATE_TRUNC(:unit, e.createdAt)', 'date_trunc(?, d0_.created_at)'];
        yield 'DAY' => ['postgresql', ['DAY' => PostgreSQL\Day::class], 'DAY(e.createdAt)', 'EXTRACT(DAY FROM d0_.created_at)'];
        yield 'HOUR' => ['postgresql', ['HOUR' => PostgreSQL\Hour::class], 'HOUR(e.createdAt)', 'EXTRACT(HOUR FROM d0_.created_at)'];
        yield 'JSON_TEXT' => ['postgresql', ['JSON_TEXT' => PostgreSQL\JsonText::class], 'JSON_TEXT(e.payload)', 'd0_.payload::text'];
        yield 'MINUTE' => ['postgresql', ['MINUTE' => PostgreSQL\Minute::class], 'MINUTE(e.createdAt)', 'EXTRACT(MINUTE FROM d0_.created_at)'];
        yield 'PostgreSQL MONTH' => ['postgresql', ['MONTH' => PostgreSQL\Month::class], 'MONTH(e.createdAt)', 'EXTRACT(MONTH FROM d0_.created_at)'];
        yield 'SECOND' => ['postgresql', ['SECOND' => PostgreSQL\Second::class], 'SECOND(e.createdAt)', 'FLOOR(EXTRACT(SECOND FROM d0_.created_at))'];
        yield 'UNACCENT of a function' => ['postgresql', ['UNACCENT' => PostgreSQL\Unaccent::class], 'UNACCENT(LOWER(e.name))', 'UNACCENT(LOWER(d0_.name))'];
        yield 'PostgreSQL YEAR of a function' => [
            'postgresql',
            ['YEAR' => PostgreSQL\Year::class, 'DATE_TRUNC' => DateTrunc::class],
            'YEAR(DATE_TRUNC(:unit, e.createdAt))',
            'EXTRACT(YEAR FROM date_trunc(?, d0_.created_at))',
        ];
    }

    /**
     * @param class-string<FunctionNode> $functionClass
     */
    #[DataProvider('dataInvalidArguments')]
    public function testTheParserRejectsAnInvalidArgumentList(string $name, string $functionClass, string $dqlExpression, string $message): void
    {
        $em = $this->createEntityManager('postgresql', [$name => $functionClass]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage($message);

        $em->createQuery(sprintf('SELECT %s FROM %s e', $dqlExpression, DqlFunctionsEvent::class))->getSQL();
    }

    public static function dataInvalidArguments(): iterable
    {
        yield 'a second argument' => ['YEAR', PostgreSQL\Year::class, 'YEAR(e.createdAt, e.id)', 'Expected Doctrine\ORM\Query\TokenType::T_CLOSE_PARENTHESIS, got \',\''];
        yield 'a missing argument' => ['DATE_TRUNC', DateTrunc::class, 'DATE_TRUNC(:unit)', 'Expected Doctrine\ORM\Query\TokenType::T_COMMA, got \')\''];
    }

    /**
     * @param array<string, class-string<FunctionNode>> $functions
     */
    private function createEntityManager(string $platform, array $functions): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfig([], true);
        $config->enableNativeLazyObjects(true);

        foreach ($functions as $name => $functionClass) {
            $config->addCustomStringFunction($name, $functionClass);
        }

        // The explicit server version selects the platform without connecting (string literals would connect to quote them)
        $params = 'mysql' === $platform
            ? ['driver' => 'pdo_mysql', 'serverVersion' => '8.0.32']
            : ['driver' => 'pdo_pgsql', 'serverVersion' => '16'];

        return new EntityManager(DriverManager::getConnection($params, $config), $config);
    }
}

#[ORM\Entity]
#[ORM\Table(name: 'dql_functions_event')]
class DqlFunctionsEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;

    #[ORM\Column(length: 50, nullable: true)]
    public ?string $name = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public \DateTimeImmutable $createdAt;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    public array $payload = [];
}
