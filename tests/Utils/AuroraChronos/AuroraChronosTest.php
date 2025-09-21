<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraChronos;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraChronos\AuroraChronos;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/AuroraChronos/AuroraChronosTest.php --no-coverage
 */
class AuroraChronosTest extends TestCase
{
    /**
     * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/AuroraChronos/AuroraChronosTest.php --no-coverage --filter testMinutesBetweenTwoDates
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
            (new AuroraChronos())->dateToMachineDate($given[0], $given[1])
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
            (new AuroraChronos())->dateToMachineDateTime($given[0], $given[1])
        );
    }

    public static function dataDateToMachineDateTime(): array
    {
        return [
            ['2010-02-01 01:02:03', ['01.02.2010 1:2:3', 'd.m.Y H:i:s']],
            ['2010-02-01 00:00:00', ['01.02.2010', 'd.m.Y']],
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
}
