<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraChronos;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraChronos\AuroraChronos;

class ConvertHumanTimeToSecondsTest extends TestCase
{
    #[DataProvider('dataConvertHumanTimeToSeconds')]
    /** @dataProvider dataConvertHumanTimeToSeconds */
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
            [3600, '1h'],
            [7200, '2H'],
            [0, '10Z'],
        ];
    }
}
