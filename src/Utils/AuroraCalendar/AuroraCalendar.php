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
        $month                = str_pad($date->format('m'), 2, '0', STR_PAD_LEFT);
        $firstDay             = $date->format('Y-m-01');
        $firstDayWeekPosition = $date->format('N'); // 1 = monday, 7 = sunday
        $lastDayOfMonth       = $date->format('t');
        $lastDayYMD           = $date->format('Y-m-t');

        $gapsBeforeFirstDay = (int)$firstDayWeekPosition - 1;
        $gapsAfterLastDay   = 7 - (int)date('N', strtotime($lastDayYMD));
        $totalDays          = ($gapsBeforeFirstDay + $lastDayOfMonth + $gapsAfterLastDay);

        return $totalDays;
    }
}
