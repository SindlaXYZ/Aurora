<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraCalendar;

use PHPUnit\Framework\Attributes\DataProvider;
use Sindla\Bundle\AuroraBundle\Utils\AuroraCalendar\AuroraCalendar;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/AuroraCalendar/AuroraCalendarTest.php --no-coverage
 */
class AuroraCalendarTest extends KernelTestCase
{
    private $kernelTest;
    private $containerTest;

    protected function setUp(): void
    {
        $this->kernelTest    = self::bootKernel();
        $this->containerTest = $this->kernelTest->getContainer();
    }

    ###################################################################################################################################################################################################

    #[DataProvider('dataWeekDaysFromPreviousMonthBeforeFirstDayOfTheMonth')]
    public function testWeekDaysFromPreviousMonthBeforeFirstDayOfTheMonth(int $expected, \DateTimeInterface $given): void
    {
        $this->assertEquals(
            $expected,
            new AuroraCalendar()->weekDaysFromPreviousMonthBeforeFirstDayOfTheMonth($given),
            'Given date: ' . $given->format('Y-m-d') . ' (' . ($given instanceof \DateTimeImmutable ? 'DateTimeImmutable' : 'DateTime') . ')'
        );
    }

    public static function dataWeekDaysFromPreviousMonthBeforeFirstDayOfTheMonth(): array
    {
        return [
            // 0 days (in the same week) before the first day of the month
            [0, new \DateTimeImmutable('2021-02-20')],
            [0, new \DateTime('2021-02-20')],

            // 3 days (in the same week) before the first day of the month
            [3, new \DateTimeImmutable('2022-12-31')],
            [3, new \DateTime('2022-12-31')],

            // 4 days (in the same week) before the first day of the month
            [4, new \DateTimeImmutable('2024-03-15')],
            [4, new \DateTime('2024-03-15')],

            // 0 days (in the same week) before the first day of the month
            [0, new \DateTimeImmutable('2024-04-01')],
            [0, new \DateTime('2024-04-01')],
        ];
    }

    ###################################################################################################################################################################################################

    #[DataProvider('dataFullWeeksDaysNumber')]
    public function testFullWeeksDaysNumber(int $expected, \DateTimeInterface $given): void
    {
        $this->assertEquals($expected, new AuroraCalendar()->fullWeeksDaysNumber($given));

        // No matter the month and the year, the number of days must be a multiple of 7
        $this->assertEquals(0, new AuroraCalendar()->fullWeeksDaysNumber($given) % 7);
    }

    public static function dataFullWeeksDaysNumber(): array
    {
        return [
            // + 0 days before + 28 days + 0 days after
            [28, new \DateTimeImmutable('2021-02-20')],
            [28, new \DateTime('2021-02-20')],

            // + 3 days before + 31 days + 1 day after
            [35, new \DateTimeImmutable('2022-12-31')],
            [35, new \DateTime('2022-12-31')],

            // + 4 days before + 31 days + 0 days after
            [35, new \DateTimeImmutable('2024-03-15')],
            [35, new \DateTime('2024-03-15')],

            // + 0 days before + 30 days + 5 days after
            [35, new \DateTimeImmutable('2024-04-01')],
            [35, new \DateTime('2024-04-01')],
        ];
    }

    ###################################################################################################################################################################################################

    #[DataProvider('dataGenerateCalendar')]
    public function testGenerateCalendar(array $auroraCalendar, array $expected): void
    {
        $this->assertEquals(count($auroraCalendar), count($expected['days']));

        foreach ($expected['days'] as $expectedDay) {
            $this->assertTrue(array_key_exists($expectedDay, $auroraCalendar));
        }
    }

    public static function dataGenerateCalendar(): array
    {
        return [
            // First day: Monday
            [
                new AuroraCalendar()->generateCalendar(new \DateTimeImmutable('2025-04-11'), null, 1, 0, 0),
                [
                    'days' => [
                        '2025-04-07', '2025-04-08', '2025-04-09', '2025-04-10', '2025-04-11', '2025-04-12', '2025-04-13'
                    ],
                ]
            ],
            [
                new AuroraCalendar()->generateCalendar(new \DateTimeImmutable('2025-04-11'), null, 1, 1, 0),
                [
                    'days' => [
                        '2025-03-31', '2025-04-01', '2025-04-02', '2025-04-03', '2025-04-04', '2025-04-05', '2025-04-06',
                        '2025-04-07', '2025-04-08', '2025-04-09', '2025-04-10', '2025-04-11', '2025-04-12', '2025-04-13'
                    ],
                ]
            ],
            [
                new AuroraCalendar()->generateCalendar(new \DateTimeImmutable('2025-04-11'), null, 1, 1, 1),
                [
                    'days' => [
                        '2025-03-31', '2025-04-01', '2025-04-02', '2025-04-03', '2025-04-04', '2025-04-05', '2025-04-06',
                        '2025-04-07', '2025-04-08', '2025-04-09', '2025-04-10', '2025-04-11', '2025-04-12', '2025-04-13',
                        '2025-04-14', '2025-04-15', '2025-04-16', '2025-04-17', '2025-04-18', '2025-04-19', '2025-04-20'
                    ],
                ]
            ],
            // First day: Sunday
            [
                new AuroraCalendar()->generateCalendar(new \DateTimeImmutable('2025-04-11'), null, 7, 0, 0),
                [
                    'days' => [
                        '2025-04-06', '2025-04-07', '2025-04-08', '2025-04-09', '2025-04-10', '2025-04-11', '2025-04-12'
                    ],
                ]
            ],
            [
                new AuroraCalendar()->generateCalendar(new \DateTimeImmutable('2025-04-11'), null, 7, 1, 0),
                [
                    'days' => [
                        '2025-03-30', '2025-03-31', '2025-04-01', '2025-04-02', '2025-04-03', '2025-04-04', '2025-04-05',
                        '2025-04-06', '2025-04-07', '2025-04-08', '2025-04-09', '2025-04-10', '2025-04-11', '2025-04-12'
                    ],
                ]
            ],
            [
                new AuroraCalendar()->generateCalendar(new \DateTimeImmutable('2025-04-11'), null, 7, 1, 1),
                [
                    'days' => [
                        '2025-03-30', '2025-03-31', '2025-04-01', '2025-04-02', '2025-04-03', '2025-04-04', '2025-04-05',
                        '2025-04-06', '2025-04-07', '2025-04-08', '2025-04-09', '2025-04-10', '2025-04-11', '2025-04-12',
                        '2025-04-13', '2025-04-14', '2025-04-15', '2025-04-16', '2025-04-17', '2025-04-18', '2025-04-19'
                    ],
                ]
            ],
        ];
    }

    ###################################################################################################################################################################################################
}
