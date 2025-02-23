<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraChronos;

class AuroraChronos
{
    const int TIME_UNIT_SECONDS = 1;
    const int TIME_UNIT_MINUTES = 2;
    const int TIME_UNIT_HOURS   = 3;
    const int TIME_UNIT_DAYS    = 4;
    const int TIME_UNIT_WEEKS   = 5;
    const int TIME_UNIT_MONTHS  = 6;
    const int TIME_UNIT_YEARS   = 7;

    /**
     * Transform/parse a human date to machine date (Y-m-d)
     *    eg: 28.09.2013 (d.m.Y) => 2013-09-28
     *
     * @param string $datetime
     * @param string $humanFormat
     * @return  string
     */
    public function dateToMachineDate($datetime, $humanFormat): string
    {
        $parsedDate = date_parse_from_format($humanFormat, $datetime);
        return $parsedDate['year'] . '-' . str_pad($parsedDate['month'], 2, 0, STR_PAD_LEFT) . '-' . str_pad($parsedDate['day'], 2, 0, STR_PAD_LEFT);
    }

    /**
     * Transform/parse a human date to machine date (Y-m-d H:i:s)
     *    eg: 28.09.2013 23:41:12 => 2013-09-28 23:41:12
     *
     * @param string $datetime
     * @param string $humanFormat
     * @return  string
     */
    public function dateToMachineDateTime($datetime, $humanFormat): string
    {
        $parsedDate = date_parse_from_format($humanFormat, $datetime);
        return $parsedDate['year'] . '-' . str_pad($parsedDate['month'], 2, 0, STR_PAD_LEFT) . '-' . str_pad($parsedDate['day'], 2, 0, STR_PAD_LEFT) . ' ' . (!empty($parsedDate['hour']) ? $parsedDate['hour'] : '00') . ':' . (!empty($parsedDate['minute']) ? $parsedDate['minute'] : '00') . ':' . (!empty($parsedDate['second']) ? $parsedDate['second'] : '00');
    }

    /**
     * Transform/parse a machine date to human date
     *    eg: 01.09.2013 => 2013-09-01
     *
     * @param mixed $datetime
     */
    public function dateToHuman($date, $humanFormat)
    {
        if (!($date instanceof \DateTime)) {
            $date = new \DateTime($date);
        }

        $parsedDate = date_parse_from_format($humanFormat, $date->format('Y-m-d H:i:s'));
        return date($humanFormat, $date->getTimestamp());
    }

    /**
     * Return full number of seconds between two dates
     *
     * @docs    https://stackoverflow.com/a/1519236/6429754
     */
    public function secondsBetweenTwoDates(\DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        return $endDate->getTimestamp() - $startDate->getTimestamp();
    }

    /**
     * Check if difference between two dates is higher than ...
     * 1 hours and 1 seconds is higher (return true) than 1 hours and 0 seconds
     *
     * @param mixed $startDate
     * @param mixed $endDate
     * @param int   $intervalUnit
     * @param int   $timeUnit
     * @return bool
     */
    public function diffIsHigherThan($startDate, $endDate, int $intervalUnit, int $timeUnit)
    {
        if (!($startDate instanceof \DateTime)) {
            $startDate = new \DateTime($startDate);
        }

        if (!($endDate instanceof \DateTime)) {
            $endDate = new \DateTime($endDate);
        }

        $interval = $startDate->diff($endDate);

        // Seconds
        if (self::TIME_UNIT_SECONDS == $timeUnit) {
            return ($this->secondsBetweenTwoDates($startDate, $endDate) > $intervalUnit);
        }

        // Minutes
        if (self::TIME_UNIT_MINUTES == $timeUnit) {
            return (
                $this->minutesBetweenTwoDates($startDate, $endDate) > $intervalUnit
                ||
                ($this->minutesBetweenTwoDates($startDate, $endDate) == $intervalUnit && $interval->format('%r%s') > 0)
            );
        }

        if (self::TIME_UNIT_HOURS == $timeUnit) {
            return (
                $this->hoursBetweenTwoDates($startDate, $endDate) > $intervalUnit
                ||
                ($this->hoursBetweenTwoDates($startDate, $endDate) == $intervalUnit && $interval->format('%r%s') > 0)
            );
        }

        if (self::TIME_UNIT_DAYS == $timeUnit) {
            return (
                $this->daysBetweenTwoDates($startDate, $endDate) > $intervalUnit
                ||
                ($this->daysBetweenTwoDates($startDate, $endDate) == $intervalUnit && $interval->format('%r%s') > 0)
            );
        }

        if (self::TIME_UNIT_WEEKS == $timeUnit) {
            return (
                ($this->daysBetweenTwoDates($startDate, $endDate) / 7) > $intervalUnit
                ||
                (($this->daysBetweenTwoDates($startDate, $endDate) / 7) == $intervalUnit && $interval->format('%r%s') > 0)
            );
        }

        if (self::TIME_UNIT_MONTHS == $timeUnit) {
            return (
                $this->monthsBetweenTwoDates($startDate, $endDate) > $intervalUnit
                ||
                ($this->monthsBetweenTwoDates($startDate, $endDate) == $intervalUnit && $interval->format('%r%s') > 0)
            );
        }

        if (self::TIME_UNIT_YEARS == $timeUnit) {
            return (
                $this->yearsBetweenTwoDates($startDate, $endDate) > $intervalUnit
                ||
                ($this->yearsBetweenTwoDates($startDate, $endDate) == $intervalUnit && $interval->format('%r%s') > 0)
            );
        }
    }

    /**
     * Return minutes number between two dates
     *
     * @param mixed $startDate
     * @param mixed $endDate
     * @return  integer
     *
     * @docs    http://stackoverflow.com/questions/2040560/finding-the-number-of-days-between-two-dates
     */
    public function minutesBetweenTwoDates($startDate, $endDate): int
    {
        if (!($startDate instanceof \DateTimeInterface)) {
            if (!($startDate instanceof \DateTime)) {
                $startDate = new \DateTime($startDate);
            }
        }

        if (!($endDate instanceof \DateTimeInterface)) {
            if (!($endDate instanceof \DateTime)) {
                $endDate = new \DateTime($endDate);
            }
        }

        return ((int)(($endDate->getTimestamp() - $startDate->getTimestamp()) / 60));
    }

    /**
     * Return hours number between two dates
     *
     * @param mixed $startDate
     * @param mixed $endDate
     * @return  integer
     *
     * @docs    http://stackoverflow.com/questions/2040560/finding-the-number-of-days-between-two-dates
     */
    public function hoursBetweenTwoDates($startDate, $endDate): int
    {
        if (!($startDate instanceof \DateTimeInterface)) {
            if (!($startDate instanceof \DateTime)) {
                $startDate = new \DateTime($startDate);
            }
        }

        if (!($endDate instanceof \DateTimeInterface)) {
            if (!($endDate instanceof \DateTime)) {
                $endDate = new \DateTime($endDate);
            }
        }

        $interval = $startDate->diff($endDate);
        return $interval->format('%r%h');
    }

    /**
     * Return days number between two dates
     *
     * @param mixed $startDate
     * @param mixed $endDate
     * @return  integer
     *
     * @docs    http://stackoverflow.com/questions/2040560/finding-the-number-of-days-between-two-dates
     */
    public function daysBetweenTwoDates($startDate, $endDate): int
    {
        if (!($startDate instanceof \DateTimeInterface)) {
            if (!($startDate instanceof \DateTime)) {
                $startDate = new \DateTime($startDate);
            }
        }

        if (!($endDate instanceof \DateTimeInterface)) {
            if (!($endDate instanceof \DateTime)) {
                $endDate = new \DateTime($endDate);
            }
        }

        $interval = $startDate->diff($endDate);
        return $interval->format("%r%a");
    }

    /**
     * Return negative or positive (rounded) months number between two dates
     */
    public function monthsBetweenTwoDates(\DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        if ($this->areSameYearSameMonth($startDate, $endDate)) {
            return 0;
        }

        return (
                ((intval($endDate->format('Y')) - intval($startDate->format('Y'))) * 12)
                + (intval($endDate->format('m')) - intval($startDate->format('m')))
            ) * -1;
    }

    /**
     * Return years number between two dates
     *
     * @param mixed $startDate
     * @param mixed $endDate
     * @return  integer
     * @throws \DateMalformedStringException
     */
    public function yearsBetweenTwoDates($startDate, $endDate): int
    {
        if (!($startDate instanceof \DateTimeInterface)) {
            if (!($startDate instanceof \DateTime)) {
                $startDate = new \DateTime($startDate);
            }
        }

        if (!($endDate instanceof \DateTimeInterface)) {
            if (!($endDate instanceof \DateTime)) {
                $endDate = new \DateTime($endDate);
            }
        }

        return $startDate->diff($endDate)->y;
    }

    public function getUniqueWeeksInRange(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $weeks    = [];
        $interval = new \DateInterval('P1D');
        $period   = new \DatePeriod($start, $interval, $end->modify('+1 day'));

        foreach ($period as $date) {
            $week     = (int)$date->format('W');
            $year     = (int)$date->format('Y');
            $weekYear = sprintf('%d-%02d', $year, $week);

            if (!isset($weeks[$weekYear])) {
                $weeks[$weekYear] = [
                    'week'           => $week,
                    'year'           => $year,
                    'firstDayOfWeek' => $date->format('Y-m-d'),
                    'lastDayOfWeek'  => $date->modify('next Sunday')->format('Y-m-d'),
                ];
            }
        }

        return array_values($weeks);
    }

    public function seconds2HMS(int $secs, ?bool $cutHourIfZero = false): string
    {
        if ($secs < 0) {
            return false;
        }

        $m = (int)($secs / 60);
        $s = $secs % 60;
        $h = (int)($m / 60);
        $m = $m % 60;

        $m = str_pad($m, 2, '0', STR_PAD_LEFT);
        $h = str_pad($h, 2, '0', STR_PAD_LEFT);
        $s = str_pad($s, 2, '0', STR_PAD_LEFT);

        if ('00' == $h && $cutHourIfZero) {
            return $m . ':' . $s;
        } else {
            return $h . ':' . $m . ':' . $s;
        }
    }

    public function seconds2HM(int $secs, bool $roundUp = false): string
    {
        if ($secs < 0) {
            return false;
        }

        $m = (int)($secs / 60);
        $s = $secs % 60;
        $h = (int)($m / 60);
        $m = $m % 60;

        $m = str_pad($m, 2, '0', STR_PAD_LEFT);
        $h = str_pad($h, 2, '0', STR_PAD_LEFT);
        $s = str_pad($s, 2, '0', STR_PAD_LEFT);

        if ($roundUp && $s >= 30) {
            $m++;
        }

        return $h . ':' . $m;
    }

    public function areSameYearSameMonth(\DateTimeInterface $date1, \DateTimeInterface $date2): bool
    {
        return $date1->format('Y-m') == $date2->format('Y-m');
    }

    public function isDateValid(string $date, $format = 'Y-m-d H:i:s'): bool
    {
        $datetime = \DateTime::createFromFormat($format, $date);
        return $datetime && $datetime->format($format) == $date;
    }


    /**
     * Convert a time string to seconds
     *   eg: 1h => 3600, 2d => 172800, 3w => 1814400, 4m => 10368000, 5y => 157680000
     */
    function convertHumanTimeToSeconds(string $timeStr): int
    {
        $timeUnits = [
            'h' => 3600,          // 1 hour = 3600 seconds
            'd' => 86400,         // 1 day = 86400 seconds
            'w' => 604800,        // 1 week = 604800 seconds
            'm' => 2592000,       // 1 month (30 days) = 2592000 seconds
            'y' => 31536000       // 1 year (365 days) = 31536000 seconds
        ];

        preg_match('/(\d+)([hdwmy])/', $timeStr, $matches);

        if (!$matches) {
            return 0;
        }

        $number = (int)$matches[1];
        $unit   = $matches[2];

        return $number * $timeUnits[$unit];
    }
}
