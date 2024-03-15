<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/FakeTest.php --no-coverage
 */
class FakeTest extends TestCase
{
    ###################################################################################################################################################################################################

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

    ###################################################################################################################################################################################################
}
