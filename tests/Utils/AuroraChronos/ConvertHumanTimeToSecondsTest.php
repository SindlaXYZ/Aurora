<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraChronos;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraChronos\AuroraChronos;

class ConvertHumanTimeToSecondsTest extends TestCase
{
    #[DataProvider('dataConvertHumanTimeToSeconds')]
    public function testConvertHumanTimeToSeconds(int $expected, string $input): void
    {
        $chronos = new AuroraChronos();
        $this->assertSame($expected, $chronos->convertHumanTimeToSeconds($input));
    }

    /**
     * @return array<int, array{0:int,1:string}>
     */
    public static function dataConvertHumanTimeToSeconds(): array
    {
        return [
            [30, '30s'],
            [3600, '1h'],
            [7200, '2H'],
            [10800, ' 3h '],
            [432000, '5 d'],
            [0, '10Z'],
        ];
    }
}
