<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraCalendar;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraCalendar\AuroraCalendar;

class AuroraCalendarMutationTest extends TestCase
{
    public function testWeekDaysFromPreviousMonthBeforeFirstDayDoesNotMutateDateTime(): void
    {
        $calendar = new AuroraCalendar();
        $date     = new \DateTime('2024-09-15 10:20:30', new \DateTimeZone('Europe/Bucharest'));
        $original = $date->format('c');

        $calendar->weekDaysFromPreviousMonthBeforeFirstDayOfTheMonth($date);

        self::assertSame($original, $date->format('c'));
    }

    public function testWeekDaysFromPreviousMonthBeforeFirstDayMatchesImmutableResult(): void
    {
        $calendar  = new AuroraCalendar();
        $mutable   = new \DateTime('2024-09-15 10:20:30', new \DateTimeZone('Europe/Bucharest'));
        $immutable = new \DateTimeImmutable('2024-09-15 10:20:30', new \DateTimeZone('Europe/Bucharest'));

        $mutableResult   = $calendar->weekDaysFromPreviousMonthBeforeFirstDayOfTheMonth($mutable);
        $immutableResult = $calendar->weekDaysFromPreviousMonthBeforeFirstDayOfTheMonth($immutable);

        self::assertSame($immutableResult, $mutableResult);
    }
}
