<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraCalendar;

use Sindla\Bundle\AuroraBundle\Enum\DayOfWeek;
use Sindla\Bundle\AuroraBundle\Utils\AuroraChronos\AuroraChronos;

class AuroraCalendar
{
    /**
     * Return the number of days for a full weeks calendar
     * Month's days + the number of days before the first day of the month + the number of days after the last day of the month
     * Always will return number divisible by 7 (because one full week has 7 days)
     *
     * eg:  For 2021-02-01, return 35
     *      For 2024-09-XX, return 35 (31 days for the October + 1 day from August + 3 days from November)
     *      For 2024-10-XX, return 35 (30 days for the November + 4 day from October + 1 days from December)
     */
    public function fullWeeksDaysNumber(\DateTimeInterface $date): int
    {
        $lastDayOfMonth     = intval($date->format('t'));
        $lastDayYMD         = $date->format('Y-m-t');
        $gapsBeforeFirstDay = $this->weekDaysFromPreviousMonthBeforeFirstDayOfTheMonth($date);
        $gapsAfterLastDay   = 7 - (int)date('N', strtotime($lastDayYMD));
        return ($gapsBeforeFirstDay + $lastDayOfMonth + $gapsAfterLastDay);
    }

    /**
     * Return the number of days from the previous month before the first day of the month
     * Eg:  If the first day of the month is a Wednesday, return 2
     *      If the first day of the month is a Monday, return 0
     *      If the first day of the month is a Sunday, return 6
     */
    public function weekDaysFromPreviousMonthBeforeFirstDayOfTheMonth(\DateTimeInterface $date): int
    {
        $firstDayOfMonth      = \DateTimeImmutable::createFromInterface($date)->modify('first day of this month');
        $firstDayWeekPosition = (int)$firstDayOfMonth->format('N'); // 1 = monday, 7 = sunday

        return (DayOfWeek::MONDAY->value === $firstDayWeekPosition ? 0 : $firstDayWeekPosition - 1);
    }

    /**
     * Generates a calendar as an associative array, where each key is a date formatted as "Y-m-d" and the value is an array containing:
     *  - 'date': the date as a DateTimeImmutable object
     *  - 'dayOfTheWeek': the day of the week (ISO-8601, 1 = Monday, ..., 7 = Sunday)
     *  - 'isYesterday': true if the date is yesterday
     *  - 'isToday': true if the date is today
     *  - 'isTomorrow': true if the date is tomorrow
     */
    /**
     * @return array<string, array{date: \DateTimeImmutable, dayOfTheWeek: int, isYesterday: bool, isToday: bool, isTomorrow: bool}>
     */
    function generateCalendar(
        \DateTimeInterface  $startDate,
        ?\DateTimeInterface $endDate = null,
        int                 $firstDayOfTheWeek = 1,
        int                 $weeksBeforeFirstDay = 0,
        int                 $weeksAfterLastDay = 0,
        ?\DateTimeInterface $referenceDate = null
    ): array
    {
        // Ensure positive values for weeksBeforeFirstDay and weeksAfterLastDay
        $weeksBeforeFirstDay = abs(intval($weeksBeforeFirstDay));
        $weeksAfterLastDay   = abs(intval($weeksAfterLastDay));

        // Convert $startDate to DateTimeImmutable if necessary
        $startDateImmutable = ($startDate instanceof \DateTimeImmutable)
            ? $startDate
            : \DateTimeImmutable::createFromInterface($startDate);

        $referenceTimezone = $startDateImmutable->getTimezone();

        if (null !== $referenceDate) {
            $referenceDateTime = ($referenceDate instanceof \DateTimeImmutable)
                ? $referenceDate
                : \DateTimeImmutable::createFromInterface($referenceDate);
            $referenceDateTime = $referenceDateTime->setTimezone($referenceTimezone);
        } else {
            $referenceDateTime = new \DateTimeImmutable('now', $referenceTimezone);
        }

        $todayStr     = $referenceDateTime->format('Y-m-d');
        $yesterdayStr = $referenceDateTime->modify('-1 day')->format('Y-m-d');
        $tomorrowStr  = $referenceDateTime->modify('+1 day')->format('Y-m-d');

        // Get the day number for $startDate (1 = Monday, 7 = Sunday)
        $currentDayNumber = (int)$startDateImmutable->format('N');

        // Calculate the number of days to subtract to reach the first day of the week ($firstDayOfTheWeek)
        if ($currentDayNumber >= $firstDayOfTheWeek) {
            $diff = $currentDayNumber - $firstDayOfTheWeek;
        } else {
            $diff = 7 - ($firstDayOfTheWeek - $currentDayNumber);
        }

        // Determine the beginning of the week that contains $startDate
        $weekStart = $startDateImmutable->modify("-{$diff} days");

        // Adjust the start of the calendar by subtracting additional weeks before the current week
        $calendarStart = $weekStart->modify("-{$weeksBeforeFirstDay} weeks");

        // If $endDate is provided, generate the calendar between startDate and endDate (with adjustments)
        if ($endDate !== null) {
            // Convert $endDate to DateTimeImmutable if necessary
            $endDateImmutable = ($endDate instanceof \DateTimeImmutable)
                ? $endDate
                : \DateTimeImmutable::createFromInterface($endDate);

            // Determine the last day of the week for $endDate
            // If the first day is Monday (1), then the last day is Sunday (7)
            // Otherwise, assume the last day of the week is $firstDayOfTheWeek - 1
            $lastDayOfWeek = ($firstDayOfTheWeek === 1) ? 7 : $firstDayOfTheWeek - 1;
            $endDayNumber  = (int)$endDateImmutable->format('N');

            // Calculate difference to reach the last day of the week
            if ($endDayNumber <= $lastDayOfWeek) {
                $endDiff = $lastDayOfWeek - $endDayNumber;
            } else {
                $endDiff = 7 - ($endDayNumber - $lastDayOfWeek);
            }

            // Determine the end of the week that contains $endDate
            $weekEnd = $endDateImmutable->modify("+{$endDiff} days");

            // Extend the end of the calendar by adding extra weeks after the last week
            $calendarEnd = $weekEnd->modify("+{$weeksAfterLastDay} weeks");

            // Generate the calendar from $calendarStart to $calendarEnd (inclusive)
            $calendar = [];
            for ($currentDate = $calendarStart; $currentDate <= $calendarEnd; $currentDate = $currentDate->modify('+1 day')) {
                $calendar = $this->_calendarArray($currentDate, $todayStr, $yesterdayStr, $tomorrowStr, $calendar);
            }

            return $calendar;
        } else {
            // If $endDate is not provided, calculate a fixed number of weeks for the calendar
            $totalWeeks  = 1 + $weeksBeforeFirstDay + $weeksAfterLastDay;
            $totalDays   = $totalWeeks * 7;
            $calendar    = [];
            $currentDate = $calendarStart;
            for ($i = 0; $i < $totalDays; $i++) {
                $calendar    = $this->_calendarArray($currentDate, $todayStr, $yesterdayStr, $tomorrowStr, $calendar);
                $currentDate = $currentDate->modify('+1 day');
            }
            return $calendar;
        }
    }

    /**
     * @param array<string, array{date: \DateTimeImmutable, dayOfTheWeek: int, isYesterday: bool, isToday: bool, isTomorrow: bool}> $calendar
     * @return array<string, array{date: \DateTimeImmutable, dayOfTheWeek: int, isYesterday: bool, isToday: bool, isTomorrow: bool}>
     */
    private function _calendarArray(
        \DateTimeImmutable $currentDate,
        string $todayStr,
        string $yesterdayStr,
        string $tomorrowStr,
        array $calendar
    ): array
    {
        $key            = $currentDate->format('Y-m-d');
        $dayOfTheWeek   = (int)$currentDate->format('N');
        $calendar[$key] = [
            'date'         => $currentDate,
            'dayOfTheWeek' => $dayOfTheWeek,
            'isYesterday'  => $key === $yesterdayStr,
            'isToday'      => $key === $todayStr,
            'isTomorrow'   => $key === $tomorrowStr,
        ];
        return $calendar;
    }
}
