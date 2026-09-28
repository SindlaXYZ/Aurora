<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraChronos;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraChronos\AuroraChronos;

/**
 * clear; php vendor/phpunit/phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/AuroraChronos/AuroraChronosTest.php --no-coverage
 */
class AuroraChronosTest extends TestCase
{
    /**
     * clear; php vendor/phpunit/phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/AuroraChronos/AuroraChronosTest.php --no-coverage --filter testMinutesBetweenTwoDates
     */
    public function testMinutesBetweenTwoDates(): void
    {
        $Chronos = new AuroraChronos();

        foreach ([
                     [
                         'startDate' => '2010-01-01 11:12:13',
                         'endDate'   => '2010-01-01 11:12:13',
                         'expected'  => 0
                     ],
                     [
                         'startDate' => '2010-01-01 11:12:13',
                         'endDate'   => '2010-01-01 11:13:13',
                         'expected'  => 1
                     ],
                     [
                         'startDate' => '2010-01-01 11:13:13',
                         'endDate'   => '2010-01-01 11:12:13',
                         'expected'  => -1
                     ],
                     [
                         'startDate' => '2010-01-01 00:00:00',
                         'endDate'   => '2010-01-02 23:59:59',
                         'expected'  => 2879
                     ]
                 ] as $test) {
            $this->assertEquals($test['expected'], $Chronos->minutesBetweenTwoDates($test['startDate'], $test['endDate']), json_encode($test));
        }
    }

    public function testDiffIsHigherThan()
    {
        $Chronos = new AuroraChronos();

        foreach ([
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-01 11:12:13',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_SECONDS,
                         'expected'     => false
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-01 11:12:14',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_SECONDS,
                         'expected'     => false
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-01 11:12:14',
                         'interval'     => 0,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_SECONDS,
                         'expected'     => true
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-01 11:12:15',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_SECONDS,
                         'expected'     => true
                     ],
                     [
                         'startDate'    => '2010-01-01 12:12:13',
                         'endDate'      => '2010-01-01 11:12:13',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_MINUTES,
                         'expected'     => false
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-01 11:12:13',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_MINUTES,
                         'expected'     => false
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-01 11:13:13',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_MINUTES,
                         'expected'     => false
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-01 11:13:14',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_MINUTES,
                         'expected'     => true
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-01 12:12:13',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_HOURS,
                         'expected'     => false
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-01 12:12:14',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_HOURS,
                         'expected'     => true
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-01 12:13:13',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_HOURS,
                         'expected'     => true
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-02 11:12:13',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_DAYS,
                         'expected'     => false
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-02 12:12:13',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_DAYS,
                         'expected'     => true
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-08 11:12:13',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_WEEKS,
                         'expected'     => false
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-08 11:12:14',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_WEEKS,
                         'expected'     => true
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-08 12:12:13',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_WEEKS,
                         'expected'     => true
                     ],
                     [
                         'startDate'    => '2024-01-01 00:00:00',
                         'endDate'      => '2024-03-01 00:00:00',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_MONTHS,
                         'expected'     => true
                     ],
                     [
                         'startDate'    => '2024-01-01 00:00:00',
                         'endDate'      => '2024-02-01 00:00:00',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_MONTHS,
                         'expected'     => false
                     ],
                     [
                         'startDate'    => '2024-01-01 00:00:00',
                         'endDate'      => '2024-02-01 00:00:01',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_MONTHS,
                         'expected'     => true
                     ],
                     [
                         'startDate'    => '2024-01-31 00:00:00',
                         'endDate'      => '2024-02-28 00:00:00',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_MONTHS,
                         'expected'     => false
                     ],
                     [
                         'startDate'    => '2024-01-31 00:00:00',
                         'endDate'      => '2024-03-02 00:00:01',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_MONTHS,
                         'expected'     => true
                     ],
                     [
                         'startDate'    => '2024-02-01 00:00:00',
                         'endDate'      => '2024-01-01 00:00:00',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_MONTHS,
                         'expected'     => false
                     ],
                     [
                         'startDate'    => '2020-01-01 00:00:00',
                         'endDate'      => '2023-01-01 00:00:00',
                         'interval'     => 2,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_YEARS,
                         'expected'     => true
                     ],
                     [
                         'startDate'    => '2024-01-01 00:00:00',
                         'endDate'      => '2023-01-01 00:00:00',
                         'interval'     => 0,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_YEARS,
                         'expected'     => false
                     ],
                     [
                         'startDate'    => '2020-01-01 00:00:00',
                         'endDate'      => '2022-01-01 00:00:01',
                         'interval'     => 2,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_YEARS,
                         'expected'     => true
                     ],
                 ] as $test) {
            $this->assertEquals($test['expected'], $Chronos->diffIsHigherThan($test['startDate'], $test['endDate'], $test['interval'], $test['intervalUnit']), json_encode($test));
        }
    }

    public function testDateToHuman()
    {
        $Chronos = new AuroraChronos();

        foreach ([
                     [
                         'date'        => '2010-01-01 11:12:13',
                         'humanFormat' => 'Y-m-d',
                         'expected'    => '2010-01-01'
                     ],
                     [
                         'date'        => '2010-12-01 11:12:13',
                         'humanFormat' => 'd/m/Y',
                         'expected'    => '01/12/2010'
                     ],
                     [
                         'date'        => '2010-12-01 11:12:13',
                         'humanFormat' => 'd/m/Y H:i',
                         'expected'    => '01/12/2010 11:12'
                     ],
                 ] as $test) {
            $this->assertEquals($test['expected'], $Chronos->dateToHuman($test['date'], $test['humanFormat']), json_encode($test));
        }
    }

    #[DataProvider('dataDateToMachineDate')]
    public function testDateToMachineDate(string $expected, array $given): void
    {
        $this->assertSame(
            $expected,
            new AuroraChronos()->dateToMachineDate($given[0], $given[1])
        );
    }

    public static function dataDateToMachineDate(): array
    {
        return [
            ['2013-09-28', ['28.09.2013', 'd.m.Y']],
            ['2020-03-02', ['31.02.2020', 'd.m.Y']],
        ];
    }

    #[DataProvider('dataDateToMachineDateTime')]
    public function testDateToMachineDateTime(string $expected, array $given): void
    {
        $this->assertSame(
            $expected,
            new AuroraChronos()->dateToMachineDateTime($given[0], $given[1])
        );
    }

    public static function dataDateToMachineDateTime(): array
    {
        return [
            ['2010-02-01 01:02:03', ['01.02.2010 1:2:3', 'd.m.Y H:i:s']],
            ['2010-02-01 00:00:00', ['01.02.2010', 'd.m.Y']],
        ];
    }

    #[DataProvider('dataInDaysRange')]
    public function testInDaysRange(
        \DateTimeImmutable $date,
        int                $pastDays,
        int                $futureDays,
        bool               $expected
    ): void
    {
        $chronos = new AuroraChronos();

        $this->assertSame(
            $expected,
            $chronos->inDaysRange($date, $pastDays, $futureDays)
        );
    }

    public static function dataInDaysRange(): iterable
    {
        $now          = new \DateTimeImmutable();
        $startOfToday = $now->setTime(0, 0, 0);
        $endOfToday   = $now->setTime(23, 59, 59);

        $defaultPastDays   = 2;
        $defaultFutureDays = 3;

        yield 'inside range' => [
            $now->modify('-1 day'),
            $defaultPastDays,
            $defaultFutureDays,
            true,
        ];

        yield 'at lower bound' => [
            $startOfToday->modify("-{$defaultPastDays} days"),
            $defaultPastDays,
            $defaultFutureDays,
            true,
        ];

        yield 'before lower bound' => [
            $startOfToday->modify("-{$defaultPastDays} days -1 second"),
            $defaultPastDays,
            $defaultFutureDays,
            false,
        ];

        yield 'at upper bound' => [
            $endOfToday->modify("+{$defaultFutureDays} days"),
            $defaultPastDays,
            $defaultFutureDays,
            true,
        ];

        yield 'after upper bound' => [
            $endOfToday->modify("+{$defaultFutureDays} days +1 second"),
            $defaultPastDays,
            $defaultFutureDays,
            false,
        ];
    }

    public function testSecondsBetweenTwoDates()
    {
        $Chronos = new AuroraChronos();

        foreach ([
                     [
                         'given'    =>
                             [
                                 'start' => new \DateTimeImmutable('2010-01-01 11:12:13'),
                                 'end'   => new \DateTimeImmutable('2010-01-01 11:12:13')
                             ],
                         'expected' => 0
                     ],
                     [
                         'given'    =>
                             [
                                 'start' => new \DateTimeImmutable('2010-01-01 11:12:13'),
                                 'end'   => new \DateTimeImmutable('2010-01-01 11:12:14')
                             ],
                         'expected' => 1
                     ],
                     [
                         'given'    =>
                             [
                                 'start' => new \DateTimeImmutable('2010-01-01 11:12:13'),
                                 'end'   => new \DateTimeImmutable('2010-01-01 11:13:13')
                             ],
                         'expected' => 60
                     ],
                     [
                         'given'    =>
                             [
                                 'start' => new \DateTimeImmutable('2010-01-01 11:12:13'),
                                 'end'   => new \DateTimeImmutable('2010-01-01 11:13:14')
                             ],
                         'expected' => 61
                     ]
                 ] as $test) {
            $this->assertEquals($test['expected'], $Chronos->secondsBetweenTwoDates($test['given']['start'], $test['given']['end']), json_encode($test));
        }
    }

    ###################################################################################################################################################################################################

    #[DataProvider('dataAreSameYearSameMonth')]
    public function testAreSameYearSameMonth(bool $expected, array $given): void
    {
        $this->assertEquals(
            $expected,
            new AuroraChronos()->areSameYearSameMonth($given[0], $given[1]),
            'Given dates: ' . $given[0]->format('Y-m-d') . ' & ' . $given[1]->format('Y-m-d')
        );
    }

    public static function dataAreSameYearSameMonth(): array
    {
        return [
            [true, [new \DateTimeImmutable('2021-02-20'), new \DateTimeImmutable('2021-02-01')]],
            [true, [new \DateTime('2021-02-20'), new \DateTimeImmutable('2021-02-01')]],
            [true, [new \DateTimeImmutable('2021-02-20'), new \DateTime('2021-02-01')]],

            [false, [new \DateTimeImmutable('2021-02-20'), new \DateTimeImmutable('2022-02-01')]],
            [false, [new \DateTime('2021-02-20'), new \DateTimeImmutable('2022-02-01')]],
            [false, [new \DateTimeImmutable('2021-02-20'), new \DateTime('2022-02-01')]],
        ];
    }

    ###################################################################################################################################################################################################

    #[DataProvider('dataMonthsBetweenTwoDates')]
    public function testMonthsBetweenTwoDates(int $expected, array $given): void
    {
        $this->assertEquals(
            $expected,
            new AuroraChronos()->monthsBetweenTwoDates($given[0], $given[1]),
            'Given dates: ' . $given[0]->format('Y-m-d') . ' & ' . $given[1]->format('Y-m-d')
        );
    }

    public static function dataMonthsBetweenTwoDates(): array
    {
        return [
            [0, [new \DateTime('2021-02-20'), new \DateTime('2021-02-20')]],
            [0, [new \DateTime('2021-02-20'), new \DateTimeImmutable('2021-02-20')]],
            [0, [new \DateTimeImmutable('2021-02-20'), new \DateTime('2021-02-20')]],
            [0, [new \DateTimeImmutable('2021-02-20'), new \DateTimeImmutable('2021-02-20')]],

            [-1, [new \DateTime('2021-01-20'), new \DateTime('2021-02-20')]],
            [-1, [new \DateTime('2021-01-20'), new \DateTimeImmutable('2021-02-20')]],
            [-1, [new \DateTimeImmutable('2021-01-20'), new \DateTime('2021-02-20')]],
            [-1, [new \DateTimeImmutable('2021-01-20'), new \DateTimeImmutable('2021-02-20')]],

            [-1, [new \DateTime('2024-01-20'), new \DateTime('2024-02-01')]],
            [-1, [new \DateTime('2024-01-20'), new \DateTimeImmutable('2024-02-01')]],
            [-1, [new \DateTimeImmutable('2024-01-20'), new \DateTime('2024-02-01')]],
            [-1, [new \DateTimeImmutable('2024-01-20'), new \DateTimeImmutable('2024-02-01')]],

            [1, [new \DateTime('2024-03-29'), new \DateTime('2024-02-01')]],
            [1, [new \DateTime('2024-03-29'), new \DateTimeImmutable('2024-02-01')]],
            [1, [new \DateTimeImmutable('2024-03-29'), new \DateTime('2024-02-01')]],
            [1, [new \DateTimeImmutable('2024-03-29'), new \DateTimeImmutable('2024-02-01')]],

            [1, [new \DateTime('2024-03-01'), new \DateTime('2024-02-28')]],
            [1, [new \DateTime('2024-03-01'), new \DateTimeImmutable('2024-02-28')]],
            [1, [new \DateTimeImmutable('2024-03-01'), new \DateTime('2024-02-28')]],
            [1, [new \DateTimeImmutable('2024-03-01'), new \DateTimeImmutable('2024-02-28')]],
        ];
    }

    public function testGetUniqueWeeksInRangeClampsStartAndEndOfFirstWeek(): void
    {
        $chronos = new AuroraChronos();

        $start = new \DateTimeImmutable('2024-01-31');
        $end   = new \DateTimeImmutable('2024-02-01');

        $weeks = $chronos->getUniqueWeeksInRange($start, $end);

        self::assertCount(1, $weeks);
        self::assertSame('2024-01-31', $weeks[0]['firstDayOfWeek']);
        self::assertSame('2024-02-01', $weeks[0]['lastDayOfWeek']);
    }

    public function testGetUniqueWeeksInRangeKeepsFinalWeekWithinBounds(): void
    {
        $chronos = new AuroraChronos();

        $start = new \DateTimeImmutable('2024-03-01');
        $end   = new \DateTimeImmutable('2024-03-12');

        $weeks = $chronos->getUniqueWeeksInRange($start, $end);

        self::assertNotEmpty($weeks);
        self::assertSame('2024-03-01', $weeks[0]['firstDayOfWeek']);

        $lastWeek = end($weeks);

        self::assertIsArray($lastWeek);
        self::assertSame('2024-03-12', $lastWeek['lastDayOfWeek']);

        foreach ($weeks as $week) {
            self::assertGreaterThanOrEqual($start->format('Y-m-d'), $week['firstDayOfWeek']);
            self::assertLessThanOrEqual($end->format('Y-m-d'), $week['lastDayOfWeek']);
        }
    }

    public function testSeconds2HMS(): void
    {
        $Chronos = new AuroraChronos();

        $this->assertSame('00:01:01', $Chronos->seconds2HMS(61));
        $this->assertFalse($Chronos->seconds2HMS(-1));
    }

    public function testSeconds2HM(): void
    {
        $Chronos = new AuroraChronos();

        $this->assertSame('00:01', $Chronos->seconds2HM(60));
        $this->assertFalse($Chronos->seconds2HM(-1));
    }

    public function testDateToHumanRespectsTimezone(): void
    {
        $previousTz = date_default_timezone_get();
        date_default_timezone_set('UTC');

        $Chronos = new AuroraChronos();
        $date    = new \DateTime('2010-01-01 12:00:00', new \DateTimeZone('Europe/Bucharest'));

        $this->assertSame('01.01.2010 12:00', $Chronos->dateToHuman($date, 'd.m.Y H:i'));

        date_default_timezone_set($previousTz);
    }

    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

    #[DataProvider('providerMonthFromYearAndWeek')]
    public function testMonthFromYearAndWeek(int $year, int $week, string $day, int $expectedMonth): void
    {
        $chronos = new AuroraChronos();
        self::assertSame($expectedMonth, $chronos->monthFromYearAndWeek($year, $week, $day));
    }

    public static function providerMonthFromYearAndWeek(): array
    {
        $data = [
            // ISO week 1 of 2024 starts on 2024-01-01 (Monday) => January
            [2024, 1, AuroraChronos::DAY_MONDAY, 1],
            // ISO week 1 of 2020 starts on 2019-12-30 (Monday) => December
            [2020, 1, AuroraChronos::DAY_MONDAY, 12],
            // Week 52 of 2021 spans 2021-12-27..2022-01-02 => December
            [2021, 52, AuroraChronos::DAY_MONDAY, 12],
            // ISO week 1 of 2022 starts on 2022-01-03 => January
            [2022, 1, AuroraChronos::DAY_MONDAY, 1],
            // ISO week 53 of 2015 spans 2015-12-28..2016-01-03 => December
            [2015, 53, AuroraChronos::DAY_MONDAY, 12],
        ];

        return $data;
    }

    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

    #[DataProvider('providerMonthFromYearAndWeekMost')]
    public function testMonthFromYearAndWeekWithMostDay(int $year, int $week, int $expectedMonth): void
    {
        $chronos = new AuroraChronos();

        self::assertSame(
            $expectedMonth,
            $chronos->monthFromYearAndWeek($year, $week, AuroraChronos::DAY_MOST)
        );
    }

    public static function providerMonthFromYearAndWeekMost(): array
    {
        return [
            // ISO week 1 of 2020 spans 2019-12-30..2020-01-05 with 5 days in January => January
            [2020, 1, 1],
            // ISO week 10 of 2024 is entirely within March => March
            [2024, 10, 3],
            // ISO week 53 of 2015 spans 2015-12-28..2016-01-03 with 4 days in December => December
            [2015, 53, 12],
        ];
    }

    #[DataProvider('dataMonthFromYearAndWeekOfEveryDay')]
    public function testMonthFromYearAndWeekOfEveryDay(string $day, int $expectedMonth): void
    {
        // ISO week 5 of 2024 spans Monday 2024-01-29 .. Sunday 2024-02-04
        $this->assertSame($expectedMonth, new AuroraChronos()->monthFromYearAndWeek(2024, 5, $day));
    }

    public static function dataMonthFromYearAndWeekOfEveryDay(): array
    {
        return [
            'tuesday'          => [AuroraChronos::DAY_TUESDAY, 1],
            'wednesday'        => [AuroraChronos::DAY_WEDNESDAY, 1],
            'thursday'         => [AuroraChronos::DAY_THURSDAY, 2],
            'friday'           => [AuroraChronos::DAY_FRIDAY, 2],
            'saturday'         => [AuroraChronos::DAY_SATURDAY, 2],
            'sunday'           => [AuroraChronos::DAY_SUNDAY, 2],
            'case-insensitive' => ['Wednesday', 1],
            'most, uppercase'  => ['MOST', 2],
        ];
    }

    public function testMonthFromYearAndWeekRejectsAnUnknownDay(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new AuroraChronos()->monthFromYearAndWeek(2024, 5, 'someday');
    }

    public function testDateToMachineDateFallsBackToTheFreeFormatParser(): void
    {
        $chronos = new AuroraChronos();

        // A machine date does not match the human format: it is kept
        $this->assertSame('2013-09-28', $chronos->dateToMachineDate('2013-09-28', 'd.m.Y'));
        $this->assertSame('2010-02-01 01:02:03', $chronos->dateToMachineDateTime('2010-02-01 01:02:03', 'd.m.Y H:i:s'));
    }

    #[DataProvider('dataDiffIsHigherThanOfDateTimeObjects')]
    public function testDiffIsHigherThanOfDateTimeObjects(bool $expected, string $startDate, string $endDate, int $interval, int $timeUnit): void
    {
        $timezone = new \DateTimeZone('UTC');

        $this->assertSame(
            $expected,
            new AuroraChronos()->diffIsHigherThan(new \DateTime($startDate, $timezone), new \DateTime($endDate, $timezone), $interval, $timeUnit)
        );
    }

    public static function dataDiffIsHigherThanOfDateTimeObjects(): array
    {
        return [
            'minutes, higher'              => [true, '2010-01-01 10:00:00', '2010-01-01 10:03:00', 2, AuroraChronos::TIME_UNIT_MINUTES],
            'hours, higher'                => [true, '2010-01-01 10:00:00', '2010-01-01 13:00:00', 2, AuroraChronos::TIME_UNIT_HOURS],
            'days, higher'                 => [true, '2010-01-01 10:00:00', '2010-01-04 10:00:00', 2, AuroraChronos::TIME_UNIT_DAYS],
            'days, lower'                  => [false, '2010-01-01 10:00:00', '2010-01-02 10:00:00', 2, AuroraChronos::TIME_UNIT_DAYS],
            'weeks, higher'                => [true, '2010-01-01 10:00:00', '2010-01-22 10:00:00', 2, AuroraChronos::TIME_UNIT_WEEKS],
            'weeks, equal plus a few days' => [true, '2010-01-01 10:00:00', '2010-01-10 10:00:00', 1, AuroraChronos::TIME_UNIT_WEEKS],
            'weeks, reversed'              => [false, '2010-01-22 10:00:00', '2010-01-01 10:00:00', 1, AuroraChronos::TIME_UNIT_WEEKS],
            'years, lower'                 => [false, '2020-01-01 00:00:00', '2021-06-01 00:00:00', 2, AuroraChronos::TIME_UNIT_YEARS],
            'unknown time unit'            => [false, '2010-01-01 10:00:00', '2030-01-01 10:00:00', 1, 99],
        ];
    }

    public function testHoursDaysAndYearsBetweenTwoDateStrings(): void
    {
        $chronos = new AuroraChronos();

        // Full hours only, negative when the end date is before the start date
        $this->assertSame(2, $chronos->hoursBetweenTwoDates('2010-01-01 10:00:00', '2010-01-01 12:59:59'));
        $this->assertSame(-2, $chronos->hoursBetweenTwoDates('2010-01-01 12:59:59', '2010-01-01 10:00:00'));

        // 2024 is a leap year
        $this->assertSame(3, $chronos->daysBetweenTwoDates('2024-02-27', '2024-03-01'));
        $this->assertSame(-3, $chronos->daysBetweenTwoDates('2024-03-01', '2024-02-27'));

        $this->assertSame(3, $chronos->yearsBetweenTwoDates('2000-02-29', '2004-02-28'));
        $this->assertSame(4, $chronos->yearsBetweenTwoDates('2000-02-29', '2004-02-29'));
    }

    public function testSeconds2HMSCanCutTheHourWhenZero(): void
    {
        $chronos = new AuroraChronos();

        $this->assertSame('01:01:01', $chronos->seconds2HMS(3661));
        $this->assertSame('25:00:00', $chronos->seconds2HMS(90000));
        $this->assertSame('01:01', $chronos->seconds2HMS(61, true));
        $this->assertSame('01:01:01', $chronos->seconds2HMS(3661, true));
    }

    #[DataProvider('dataSeconds2HMRoundUp')]
    public function testSeconds2HMRoundUp(string $expected, int $seconds, bool $roundUp): void
    {
        $this->assertSame($expected, new AuroraChronos()->seconds2HM($seconds, $roundUp));
    }

    public static function dataSeconds2HMRoundUp(): array
    {
        return [
            'below half a minute'        => ['00:01', 89, true],
            'half a minute'              => ['00:02', 90, true],
            'rounded up to the hour'     => ['01:00', 3599, true],
            'truncated without round up' => ['00:59', 3599, false],
        ];
    }

    #[DataProvider('dataIsDateValid')]
    public function testIsDateValid(bool $expected, string $date, string $format): void
    {
        $this->assertSame($expected, new AuroraChronos()->isDateValid($date, $format));
    }

    public static function dataIsDateValid(): array
    {
        return [
            'leap day'      => [true, '2024-02-29 13:14:15', 'Y-m-d H:i:s'],
            'no leap day'   => [false, '2023-02-29 13:14:15', 'Y-m-d H:i:s'],
            'invalid hour'  => [false, '2024-01-01 24:00:00', 'Y-m-d H:i:s'],
            'missing time'  => [false, '2024-02-29', 'Y-m-d H:i:s'],
            'custom format' => [true, '29.02.2024', 'd.m.Y'],
            'invalid month' => [false, '2024-13-01', 'Y-m-d'],
            'not a date'    => [false, 'not a date', 'Y-m-d'],
        ];
    }

    public function testIsDateValidUsesTheDateTimeFormatByDefault(): void
    {
        $chronos = new AuroraChronos();

        $this->assertTrue($chronos->isDateValid('2024-01-02 03:04:05'));
        $this->assertFalse($chronos->isDateValid('2024-01-02'));
    }

    #[DataProvider('dataIsDateBetween')]
    public function testIsDateBetween(bool $expected, string $date): void
    {
        $timezone = new \DateTimeZone('UTC');
        $start    = new \DateTimeImmutable('2024-03-01 00:00:00', $timezone);
        $end      = new \DateTimeImmutable('2024-03-31 23:59:59', $timezone);

        $this->assertSame($expected, new AuroraChronos()->isDateBetween(new \DateTimeImmutable($date), $start, $end));
    }

    public static function dataIsDateBetween(): array
    {
        return [
            'inside'                    => [true, '2024-03-15 12:00:00+00:00'],
            'at the start'              => [true, '2024-03-01 00:00:00+00:00'],
            'at the end'                => [true, '2024-03-31 23:59:59+00:00'],
            'one second before'         => [false, '2024-02-29 23:59:59+00:00'],
            'one second after'          => [false, '2024-04-01 00:00:00+00:00'],
            // The same instants in other timezones
            'end in another timezone'   => [true, '2024-04-01 01:59:59+02:00'],
            'after in another timezone' => [false, '2024-03-31 20:00:00-04:00'],
        ];
    }

    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
}
