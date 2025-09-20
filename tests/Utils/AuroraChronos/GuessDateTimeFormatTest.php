<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraChronos;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraChronos\AuroraChronos;

class GuessDateTimeFormatTest extends TestCase
{
    #[DataProvider('provideGuessableFormats')]
    public function testGuessDateTimeFormat(?string $expected, string $input): void
    {
        $chronos = new AuroraChronos();

        $this->assertSame($expected, $chronos->guessDateTimeFormat($input));
    }

    /**
     * @return array<int, array{0: ?string, 1: string}>
     */
    public static function provideGuessableFormats(): array
    {
        return [
            ['Y-m-d', '2024-05-30'],
            ['Y-m-d H:i:s', '2024-05-30 14:23:59'],
            ['Y-m-d H:i:s.v', '2024-05-30 14:23:59.123'],
            ['Y-m-d\\TH:i:s', '2024-05-30T14:23:59'],
            ['Y-m-d\\TH:i:s.v', '2024-05-30T14:23:59.123'],
            ['Y-m-d\\TH:i:s\\Z', '2024-05-30T14:23:59Z'],
            ['Y-m-d\\TH:i:s.v\\Z', '2024-05-30T14:23:59.123Z'],
            ['Y-m-d\\TH:i:sP', '2024-05-30T14:23:59+02:00'],
            ['Y-m-d\\TH:i:s.vP', '2024-05-30T14:23:59.123+02:00'],
            ['Y-m-d\\TH:i:sO', '2024-05-30T14:23:59+0200'],
            ['Y-m-d\\TH:i:s.vO', '2024-05-30T14:23:59.123-0530'],
            ['m/d/Y', '05/30/2024'],
            ['d.m.Y', '30.05.2024'],
            [null, 'invalid-format'],
        ];
    }
}
