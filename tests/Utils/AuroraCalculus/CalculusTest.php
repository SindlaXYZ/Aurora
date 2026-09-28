<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraCalculus;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraCalculus\AuroraCalculus;

class CalculusTest extends TestCase
{
    #[DataProvider('providePercentageChange')]
    public function testPercentageChange(float $expected, float $new, float $original): void
    {
        $Calculus = new AuroraCalculus();
        $this->assertSame($expected, $Calculus->percentageChange($new, $original));
    }

    public static function providePercentageChange(): array
    {
        return [
            'increase' => [20.0, 120.0, 100.0],
            'zero-original' => [0.0, 50.0, 0.0],
            'both-zero' => [0.0, 0.0, 0.0],
        ];
    }

    #[DataProvider('dataIsPrimeNumber')]
    public function testIsPrimeNumber(bool $expected, int $number): void
    {
        $this->assertSame($expected, new AuroraCalculus()->isPrimeNumber($number));
    }

    public static function dataIsPrimeNumber(): array
    {
        return [
            'negative'                    => [false, -7],
            'zero'                        => [false, 0],
            'one'                         => [false, 1],
            'two'                         => [true, 2],
            'three'                       => [true, 3],
            'even'                        => [false, 4],
            'multiple of three'           => [false, 9],
            'five'                        => [true, 5],
            'square of five'              => [false, 25],
            'square of seven'             => [false, 49],
            'product of eleven, thirteen' => [false, 143],
            'prime below one hundred'     => [true, 97],
            'thousandth prime'            => [true, 7919],
            'largest prime below 10^6'    => [true, 999983],
            'product of 101 and 9901'     => [false, 1000001],
        ];
    }

    #[DataProvider('dataDistanceBetweenTwoPoints')]
    public function testDistanceBetweenTwoPoints(float $expected, array $pointA, array $pointB): void
    {
        $this->assertSame($expected, new AuroraCalculus()->distanceBetweenTwoPoints($pointA, $pointB));
    }

    public static function dataDistanceBetweenTwoPoints(): array
    {
        return [
            'same point'        => [0.0, [1, 1], [1, 1]],
            '3-4-5 triangle'    => [5.0, [0, 0], [3, 4]],
            'negative coords'   => [5.0, [-2, -3], [1, 1]],
            'order independent' => [5.0, [1, 1], [-2, -3]],
            'float coords'      => [2.5, [2.5, 0], [0, 0]],
        ];
    }

    #[DataProvider('dataResizeRectangle')]
    public function testResizeRectangleKeepsTheAspectRatio(array $expected, array $dimensions, array $newDimensions): void
    {
        $this->assertSame($expected, new AuroraCalculus()->resizeRectangle($dimensions, $newDimensions));
    }

    public static function dataResizeRectangle(): array
    {
        return [
            'square fits the smaller side'    => [['width' => 50, 'height' => 50], [100, 100], [50, 80]],
            'landscape limited by the width'  => [['width' => 100, 'height' => 50], [200, 100], [100, 100]],
            'landscape limited by the height' => [['width' => 80, 'height' => 40], [200, 100], [100, 40]],
            'portrait limited by the height'  => [['width' => 50, 'height' => 100], [100, 200], [100, 100]],
            'portrait limited by the width'   => [['width' => 40, 'height' => 80], [100, 200], [40, 100]],
        ];
    }
}
