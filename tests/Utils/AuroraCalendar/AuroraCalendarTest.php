<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraCalendar;

use PHPUnit\Framework\Attributes\DataProvider;
use Sindla\Bundle\AuroraBundle\Utils\AuroraCalendar\AuroraCalendar;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AuroraCalendarTest extends KernelTestCase
{
    ###################################################################################################################################################################################################

    #[DataProvider('dataWeekDaysFromPreviousMonthBeforeFirstDayOfTheMonth')]
    public function testWeekDaysFromPreviousMonthBeforeFirstDayOfTheMonth(int $expected, \DateTimeInterface $given): void
    {
        $this->assertEquals(
            $expected,
            (new AuroraCalendar())->weekDaysFromPreviousMonthBeforeFirstDayOfTheMonth($given),
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

    #[DataProvider('dataDaysNumber')]
    public function testDaysNumber(int $expected, \DateTimeInterface $given): void
    {
        $this->assertEquals($expected, (new AuroraCalendar())->daysNumber($given));
    }

    public static function dataDaysNumber(): array
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
}
