<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraHelper;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraHelper\AuroraHelper;

/**
 * clear; php vendor/phpunit/phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/AuroraHelper/AuroraHelperTest.php --no-coverage
 */
class AuroraHelperTest extends TestCase
{
    public function testArrayToFlattenedDotPath(): void
    {
        $Helper = new AuroraHelper();

        $nestedArray = [
            'this' => [
                [
                    'is'  => 'nested',
                    'and' => [
                        'more' => 'nested'
                    ]
                ],
                [
                    'lorem' => 'ipsum'
                ]
            ]
        ];

        $flattenedArray = [
            'this.0.is'       => 'nested',
            'this.0.and.more' => 'nested',
            'this.1.lorem'    => 'ipsum'
        ];

        $this->assertEquals($flattenedArray, $Helper->arrayToFlattenedDotPath($nestedArray));
    }

    public function testArrayMultidimensionalKeyExists(): void
    {
        $helper = new AuroraHelper();

        $array = [
            'first'  => 'value',
            'nested' => [
                'second' => [
                    'target' => 'found',
                ],
            ],
        ];

        $this->assertTrue($helper->arrayMultidimensionalKeyExists('target', $array));
        $this->assertFalse($helper->arrayMultidimensionalKeyExists('missing', $array));
    }

    public function testKsortRecursive(): void
    {
        $helper = new AuroraHelper();

        $unordered = [
            'b' => [
                'delta' => 4,
                'alpha' => 1,
            ],
            'a' => [
                'charlie' => 3,
                'bravo'   => 2,
            ],
        ];

        $expected = [
            'a' => [
                'bravo'   => 2,
                'charlie' => 3,
            ],
            'b' => [
                'alpha' => 1,
                'delta' => 4,
            ],
        ];

        $this->assertSame($expected, $helper->ksortRecursive($unordered));
    }

    public function testIsTrueAndIsFalse(): void
    {
        $helper = new AuroraHelper();

        $this->assertTrue($helper->isTrue('1'));
        $this->assertTrue($helper->isTrue('true'));
        $this->assertFalse($helper->isTrue('no'));

        $this->assertTrue($helper->isFalse('0'));
        $this->assertTrue($helper->isFalse('false'));
        $this->assertFalse($helper->isFalse('yes'));
    }
}
