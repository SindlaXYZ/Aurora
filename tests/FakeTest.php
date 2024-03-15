<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/FakeTest.php --no-coverage
 */
class FakeTest extends TestCase
{
    public function __construct($name = null, array $data = [], $dataName = '')
    {
        $this->preSetUp();
        parent::__construct($name, $data, $dataName);
    }

    ###################################################################################################################################################################################################

    #[DataProvider('dataFakeDataProvider')]
    public function testFakeDataProvider($given, $expected): void
    {
        $this->assertEquals($given, $expected);
    }

    public static function dataFakeDataProvider(): array
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
