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

    public function guessDateTimeFormat($datetime): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datetime)) {
            return 'Y-m-d';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $datetime)) {
            return 'Y-m-d H:i:s';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}$/', $datetime)) {
            return 'Y-m-d H:i:s.v';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $datetime)) {
            return 'Y-m-d\TH:i:s';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}$/', $datetime)) {
            return 'Y-m-d\TH:i:s.v';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $datetime)) {
            return 'Y-m-d\TH:i:s\Z';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $datetime)) {
            return 'Y-m-d\TH:i:s.v\Z';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+\-]\d{2}:\d{2}$/', $datetime)) {
            return 'Y-m-d\TH:i:sP';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}[+\-]\d{2}:\d{2}$/', $datetime)) {
            return 'Y-m-d\TH:i:s.vP';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+\-]\d{4}$/', $datetime)) {
            return 'Y-m-d\TH:i:sO';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}[+\-]\d{4}$/', $datetime)) {
            return 'Y-m-d\TH:i:s.vO';
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $datetime)) {
            return 'm/d/Y';
        }

        if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $datetime)) {
            return 'd.m.Y';
        }

        return null;
    }

    /**
     * Transform/parse a human date to machine date (Y-m-d)
     *    eg: 28.09.2013 (d.m.Y) => 2013-09-28
     */
    public function dateToMachineDate(string $datetime, string $humanFormat): string
    {
        $date = \DateTime::createFromFormat('!' . $humanFormat, $datetime);

        if (!$date) {
            return date('Y-m-d', strtotime($datetime));
        }

        $errors = \DateTime::getLastErrors();
        if (($errors['error_count'] ?? 0) > 0) {
            return date('Y-m-d', strtotime($datetime));
        }

        return $date->format('Y-m-d');
    }

    /**
     * Transform/parse a human date to machine date (Y-m-d H:i:s)
     *    eg: 28.09.2013 23:41:12 => 2013-09-28 23:41:12
     */
    public function dateToMachineDateTime(string $datetime, string $humanFormat): string
    {
        $dateTime = \DateTime::createFromFormat('!' . $humanFormat, $datetime);

        if (!$dateTime) {
            return date('Y-m-d H:i:s', strtotime($datetime));
        }

        $errors = \DateTime::getLastErrors();
        if (($errors['error_count'] ?? 0) > 0) {
            return date('Y-m-d H:i:s', strtotime($datetime));
        }

        return $dateTime->format('Y-m-d H:i:s');
    }

    /**
     * Transform a machine date to a human date format
     *    eg: 2013-09-01 => 01.09.2013
     */
    public function dateToHuman(string|\DateTimeInterface $date, string $humanFormat): string
    {
        if (!($date instanceof \DateTimeInterface)) {
            $date = new \DateTime($date);
        }

        return $date->format($humanFormat);
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
     * Check if the difference between two dates is higher than ...
     * 1 hour and 1 second is higher (return true) than 1 hour and 0 seconds
     */
    public function diffIsHigherThan(
        string|\DateTime $startDate,
        string|\DateTime $endDate,
        int              $intervalUnit,
        int              $timeUnit
    ): bool
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
            if ($interval->invert === 1) {
                return false;
            }

            $monthsDiff = abs($this->monthsBetweenTwoDates($startDate, $endDate));
            $hasRemainder = $interval->d > 0
                || $interval->h > 0
                || $interval->i > 0
                || $interval->s > 0
                || $interval->f > 0;

            return (
                $monthsDiff > $intervalUnit
                ||
                ($monthsDiff === $intervalUnit && $hasRemainder)
            );
        }

        if (self::TIME_UNIT_YEARS == $timeUnit) {
            if ($interval->invert === 1) {
                return false;
            }

            $yearsDiff = $this->yearsBetweenTwoDates($startDate, $endDate);
            $hasRemainder = $interval->m > 0
                || $interval->d > 0
                || $interval->h > 0
                || $interval->i > 0
                || $interval->s > 0
                || $interval->f > 0;

            return (
                $yearsDiff > $intervalUnit
                ||
                ($yearsDiff === $intervalUnit && $hasRemainder)
            );
        }

        return false;
    }

    /**
     * Return minutes number between two dates
     *
     * @docs    http://stackoverflow.com/questions/2040560/finding-the-number-of-days-between-two-dates
     */
    public function minutesBetweenTwoDates(
        string|\DateTimeInterface|\DateTime $startDate,
        string|\DateTimeInterface|\DateTime $endDate
    ): int
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
     * @docs    http://stackoverflow.com/questions/2040560/finding-the-number-of-days-between-two-dates
     */
    public function hoursBetweenTwoDates(
        string|\DateTimeInterface|\DateTime $startDate,
        string|\DateTimeInterface|\DateTime $endDate
    ): int
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

        return intdiv($endDate->getTimestamp() - $startDate->getTimestamp(), 3600);
    }

    /**
     * Return days number between two dates
     *
     * @docs    http://stackoverflow.com/questions/2040560/finding-the-number-of-days-between-two-dates
     */
    public function daysBetweenTwoDates(
        string|\DateTimeInterface|\DateTime $startDate,
        string|\DateTimeInterface|\DateTime $endDate
    ): int
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
    public function monthsBetweenTwoDates(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): int
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
     * @throws \DateMalformedStringException
     */
    public function yearsBetweenTwoDates(
        string|\DateTimeInterface|\DateTime $startDate,
        string|\DateTimeInterface|\DateTime $endDate
    ): int
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
                    'firstDayOfWeek' => (1 == (intval($date->format('N'))) ? $date->format('Y-m-d') : $date->modify('previous Monday')->format('Y-m-d')),
                    'lastDayOfWeek'  => (7 == (intval($date->format('N'))) ? $date->format('Y-m-d') : $date->modify('next Sunday')->format('Y-m-d')),
                ];
            }
        }

        return array_values($weeks);
    }

    public function seconds2HMS(int $secs, ?bool $cutHourIfZero = false): string|false
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

    public function seconds2HM(int $secs, bool $roundUp = false): string|false
    {
        if ($secs < 0) {
            return false;
        }

        $m = intdiv($secs, 60);
        $s = $secs % 60;
        $h = intdiv($m, 60);
        $m = $m % 60;

        if ($roundUp && $s >= 30) {
            $m++;
            if ($m === 60) {
                $m = 0;
                $h++;
            }
        }

        $m = str_pad((string)$m, 2, '0', STR_PAD_LEFT);
        $h = str_pad((string)$h, 2, '0', STR_PAD_LEFT);

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
     *   eg: 30s => 30, 1h => 3600, 2d => 172800, 3w => 1814400, 4m => 10368000, 5y => 157680000
     */
    function convertHumanTimeToSeconds(string $timeStr): int
    {
        $timeUnits = [
            's' => 1,             // 1 second = 1 second
            'h' => 3600,          // 1 hour = 3600 seconds
            'd' => 86400,         // 1 day = 86400 seconds
            'w' => 604800,        // 1 week = 604800 seconds
            'm' => 2592000,       // 1 month (30 days) = 2592000 seconds
            'y' => 31536000       // 1 year (365 days) = 31536000 seconds
        ];

        $timeStr = strtolower(trim($timeStr));

        if (!preg_match('/^(\d+)\s*([shdwmy])$/', $timeStr, $matches)) {
            return 0;
        }

        $number = (int)$matches[1];
        $unit   = $matches[2];

        return $number * $timeUnits[$unit];
    }
}
