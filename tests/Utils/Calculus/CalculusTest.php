<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\Calculus;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\Calculus\Calculus;

class CalculusTest extends TestCase
{
    #[DataProvider('providePercentageChange')]
    /**
     * @dataProvider providePercentageChange
     */
    public function testPercentageChange(float $expected, float $new, float $original): void
    {
        $Calculus = new Calculus();
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
}
