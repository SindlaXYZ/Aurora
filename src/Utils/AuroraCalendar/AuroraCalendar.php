<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraCalendar;

class AuroraCalendar
{
    /**
     * For a specific date, return the number of days in the month + the number of days before the first day of the month + the number of days after the last day of the month
     * eg:  For 2021-02-01, return 35
     *      For 2024-09-XX, return 35 (31 days for the October + 1 day from August + 3 days from November)
     *      For 2024-10-XX, return 35 (30 days for the November + 4 day from October + 1 days from December)
     */
    public function daysNumber(\DateTimeInterface $date): int
    {
        $month          = str_pad($date->format('m'), 2, '0', STR_PAD_LEFT);
        $firstDay       = $date->format('Y-m-01');
        $lastDayOfMonth = $date->format('t');
        $lastDayYMD     = $date->format('Y-m-t');

        $gapsBeforeFirstDay = $this->weekDaysFromPreviousMonthBeforeFirstDayOfTheMonth($date);

        $gapsAfterLastDay = 7 - (int)date('N', strtotime($lastDayYMD));
        $totalDays        = ($gapsBeforeFirstDay + $lastDayOfMonth + $gapsAfterLastDay);

        return $totalDays;
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

    public function generateCalendar(\DateTimeInterface $date, int $weeksBeforeFirstDay = 0, int $weeksAfterLastDay = 0): array
    {
        $weekDaysFromPreviousMonthBeforeFirstDayOfTheMonth = $this->weekDaysFromPreviousMonthBeforeFirstDayOfTheMonth($date);
        $calendarDays                                      = $this->daysNumber($date);
        $lastDayOfMonth                                    = intval($date->format('t'));

        for ($i = 1; $i <= $calendarDays; ++$i) {
            if ($i < ($weekDaysFromPreviousMonthBeforeFirstDayOfTheMonth + 1)) {
                $inPreviousMonth = true;
                $inNextMonth     = false;
                $calendarDate    = $date->modify("-" . ($weekDaysFromPreviousMonthBeforeFirstDayOfTheMonth - $i) . " days");
            } else if ($i > $weekDaysFromPreviousMonthBeforeFirstDayOfTheMonth + $lastDayOfMonth) {
                $inPreviousMonth = false;
                $inNextMonth     = true;
                $calendarDate    = $date->modify("+" . ($i - ($weekDaysFromPreviousMonthBeforeFirstDayOfTheMonth + $lastDayOfMonth)) . " days");
            } else {
                $inPreviousMonth = false;
                $inNextMonth     = false;
                $calendarDate    = $date->modify("+" . ($i - $weekDaysFromPreviousMonthBeforeFirstDayOfTheMonth) . " days");
            }

            $calendar[$i] = [
                'inPreviousMonth' => $inPreviousMonth,
                'inNextMonth'     => $inNextMonth,
                'isToday'         => date('Y-m-d') == $calendarDate->format('Y-m-d'),
                'date'            => $calendarDate
            ];
        }

        return $calendar;
    }
}
