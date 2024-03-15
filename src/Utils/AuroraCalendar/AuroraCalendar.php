<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraCalendar;

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

    public function generateCalendar(\DateTimeInterface $date, int $weeksBeforeFirstDay = 0, int $weeksAfterLastDay = 0): ?array
    {
        $date                                              = (new \DateTimeImmutable())->setDate($date->format('Y'), $date->format('m'), 1)->setTime(0, 0, 0);
        $weekDaysFromPreviousMonthBeforeFirstDayOfTheMonth = $this->weekDaysFromPreviousMonthBeforeFirstDayOfTheMonth($date);
        $calendarDays                                      = $this->fullWeeksDaysNumber($date);
        $calendarStartDate                                 = $date->modify("-" . $weekDaysFromPreviousMonthBeforeFirstDayOfTheMonth . " days");
        $calendar                                          = [];

        for ($i = 1; $i <= $calendarDays; ++$i) {
            $calendarDate = $calendarStartDate->modify('+' . ($i - 1) . ' days');

            $calendar[$calendarDate->format('Y-m-d')] = [
                'date'         => $calendarDate,
                'day'          => $calendarDate->format('j'),
                'dayOfTheWeek' => date('N', strtotime($date)),
                'monthsDiff'   => ((new AuroraChronos())->monthsBetweenTwoDates($calendarDate, $date)),
                'isToday'      => (date('Y-m-d') == $calendarDate->format('Y-m-d')),
            ];
        }

        return $calendar;
    }
}
