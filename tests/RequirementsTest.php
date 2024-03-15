<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/RequirementsTest.php --no-coverage
 */
class RequirementsTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    ##########################################################################################################################################################################################

    #[DataProvider('dataFakeDataProviderAttribute')]
    public function testFakeDataProviderAttribute($given, $expected): void
    {
        $this->assertEquals($given, $expected);
    }

    public static function dataFakeDataProviderAttribute(): array
    {
        return [
            [1, 1],
            ['1', "1"],
            [1, '1'],
            ['1', 1]
        ];
    }

    ##########################################################################################################################################################################################

    public function testEnvironments(): void
    {
        $this->assertEquals('test', $_ENV['APP_ENV']);
    }

    ##########################################################################################################################################################################################
}
