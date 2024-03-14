<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraCalendar;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraCalendar\AuroraCalendar;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/AuroraCalendarTest/AuroraCalendarTest.php --no-coverage
 */
class AuroraCalendarTest extends KernelTestCase
{
    #[DataProvide('dataWeekDaysFromPreviousMonthBeforeFirstDayOfTheMonth')]
    public function testWeekDaysFromPreviousMonthBeforeFirstDayOfTheMonth(int $expected, \DateTimeInterface $given): void
    {
        $this->assertEquals($expected, AuroraCalendar::weekDaysFromPreviousMonthBeforeFirstDayOfTheMonth($given));
    }

    public static function dataWeekDaysFromPreviousMonthBeforeFirstDayOfTheMonth(): array
    {
        return [
            // + 0 days before
            [0, new \DateTimeImmutable('2021-02-20')],
            [0, new \DateTime('2021-02-20')],

            // + 3 days before
            [3, new \DateTimeImmutable('2022-12-31')],
            [3, new \DateTime('2022-12-31')],

            // + 4 days before
            [4, new \DateTimeImmutable('2024-03-15')],
            [4, new \DateTime('2024-03-15')],

            // + 0 days before
            [0, new \DateTimeImmutable('2024-04-01')],
            [0, new \DateTime('2024-04-01')],
        ];
    }

    ###################################################################################################################################################################################################

    #[DataProvider('dataDaysNumber')]
    public function testDaysNumber(int $expected, \DateTimeInterface $given): void
    {
        $this->assertEquals($expected, AuroraCalendar::daysNumber($given));
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
