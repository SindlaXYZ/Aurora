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
        $date                 = $date->setDate($date->format('Y'), $date->format('m'), 1);
        $firstDayWeekPosition = $date->format('N'); // 1 = monday, 7 = sunday
        return (1 == $firstDayWeekPosition ? 0 : (int)$firstDayWeekPosition - 1);
    }

    /**
     * Generate a calendar in the form of an associative array, where each key is the date in the format Y-m-d, and the value is an array containing:
     *  - 'date': the date as a DateTimeImmutable object
     *  - 'dayOfTheWeek': the day of the week (1 = Monday, 7 = Sunday)
     *  - 'isYesterday': boolean indicating if the date is yesterday
     *  - 'isToday': boolean indicating if the date is today
     *  - 'isTomorrow': boolean indicating if the date is tomorrow
     */
    function generateCalendar(\DateTimeInterface $immutableDate, int $firstDayOfTheWeek = 1, int $weeksBeforeFirstDay = 0, int $weeksAfterLastDay = 0): array
    {
        $weeksBeforeFirstDay = abs(intval($weeksBeforeFirstDay));
        $weeksAfterLastDay   = abs(intval($weeksAfterLastDay));

        // Obtain the current day number (1 = Monday, 7 = Sunday)
        $currentDayNumber = (int)$immutableDate->format('N');

        // Calculate the number of days to subtract to reach the first day of the week ($firstDayOfTheWeek)
        if ($currentDayNumber >= $firstDayOfTheWeek) {
            $diff = $currentDayNumber - $firstDayOfTheWeek;
        } else {
            $diff = 7 - ($firstDayOfTheWeek - $currentDayNumber);
        }

        // Determine the beginning of the week that contains $date
        $weekStart = $immutableDate->modify("-{$diff} days");

        // Adjust to include previous weeks
        $calendarStart = $weekStart->modify("-{$weeksBeforeFirstDay} weeks");

        // Calculate the total number of weeks in the calendar
        $totalWeeks = 1 + $weeksBeforeFirstDay + $weeksAfterLastDay;
        $totalDays  = $totalWeeks * 7;
        $calendar   = [];

        // Calculate today's date for comparisons
        $todayStr = new \DateTimeImmutable('today')->format('Y-m-d');

        $currentDate = $calendarStart;
        for ($i = 0; $i < $totalDays; $i++) {
            $key          = $currentDate->format('Y-m-d');
            $dayOfTheWeek = (int)$currentDate->format('N');
            $isToday      = ($key === $todayStr);

            $calendar[$key] = [
                'date'         => $currentDate,
                'dayOfTheWeek' => $dayOfTheWeek,
                'isYesterday'  => $currentDate->format('Y-m-d') === new \DateTimeImmutable('yesterday')->format('Y-m-d'),
                'isToday'      => $isToday,
                'isTomorrow'   => $currentDate->format('Y-m-d') === new \DateTimeImmutable('tomorrow')->format('Y-m-d'),
            ];

            // Move to the next day
            $currentDate = $currentDate->modify('+1 day');
        }

        return $calendar;
    }
}
