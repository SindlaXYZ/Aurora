<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraArray;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraArray\AuroraArray;

class AuroraArrayTest extends TestCase
{
    private AuroraArray $helper;

    protected function setUp(): void
    {
        $this->helper = new AuroraArray();
    }

    public function testMultiDimensionalKeyExists(): void
    {
        $nested = [
            'level_one' => [
                'level_two' => [
                    'target' => 'value',
                ],
            ],
            'other_branch' => [
                'another_key' => 'something',
            ],
        ];

        $this->assertTrue($this->helper->multiDimensionalKeyExists('target', $nested));
        $this->assertFalse($this->helper->multiDimensionalKeyExists('missing', $nested));
    }

    public function testToFlattenedDotPath(): void
    {
        $source = [
            'alpha' => [
                'beta' => 'first',
                'gamma' => [
                    'delta' => 'second',
                ],
            ],
            'epsilon' => 'third',
        ];

        $expected = [
            'alpha.beta' => 'first',
            'alpha.gamma.delta' => 'second',
            'epsilon' => 'third',
        ];

        $this->assertSame($expected, $this->helper->toFlattenedDotPath($source));
    }

    public function testToFlattenedDotPathWithPrefix(): void
    {
        $source = ['key' => 'value'];

        $this->assertSame(
            ['prefix.key' => 'value'],
            $this->helper->toFlattenedDotPath($source, 'prefix.')
        );
    }

    public function testKSortRecursive(): void
    {
        $input = [
            'zeta' => [
                'beta' => 2,
                'alpha' => 1,
            ],
            'eta' => [
                'delta' => [
                    'charlie' => 3,
                    'bravo' => 4,
                ],
                'alpha' => 0,
            ],
        ];

        $expected = [
            'eta' => [
                'alpha' => 0,
                'delta' => [
                    'bravo' => 4,
                    'charlie' => 3,
                ],
            ],
            'zeta' => [
                'alpha' => 1,
                'beta' => 2,
            ],
        ];

        $this->assertSame($expected, $this->helper->kSortRecursive($input));
    }
}
