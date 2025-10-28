<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraChronos;

class AuroraChronos
{
    final const int TIME_UNIT_SECONDS = 1;
    final const int TIME_UNIT_MINUTES = 2;
    final const int TIME_UNIT_HOURS   = 3;
    final const int TIME_UNIT_DAYS    = 4;
    final const int TIME_UNIT_WEEKS   = 5;
    final const int TIME_UNIT_MONTHS  = 6;
    final const int TIME_UNIT_YEARS   = 7;

    final const string DAY_MONDAY    = 'monday';
    final const string DAY_TUESDAY   = 'tuesday';
    final const string DAY_WEDNESDAY = 'wednesday';
    final const string DAY_THURSDAY  = 'thursday';
    final const string DAY_FRIDAY    = 'friday';
    final const string DAY_SATURDAY  = 'saturday';
    final const string DAY_SUNDAY    = 'sunday';
    final const string DAY_MOST      = 'most';

    public function guessDateTimeFormat($datetime): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datetime)) {
            return 'Y-m-d';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $datetime)) {
            return 'Y-m-d H:i:s';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.(\d{1,6})$/', $datetime, $matches)) {
            return strlen($matches[1]) <= 3 ? 'Y-m-d H:i:s.v' : 'Y-m-d H:i:s.u';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}Z$/', $datetime)) {
            return 'Y-m-d H:i:s\\Z';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.(\d{1,6})Z$/', $datetime, $matches)) {
            return strlen($matches[1]) <= 3 ? 'Y-m-d H:i:s.v\\Z' : 'Y-m-d H:i:s.u\\Z';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}[+\-]\d{2}:\d{2}$/', $datetime)) {
            return 'Y-m-d H:i:sP';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.(\d{1,6})[+\-]\d{2}:\d{2}$/', $datetime, $matches)) {
            return strlen($matches[1]) <= 3 ? 'Y-m-d H:i:s.vP' : 'Y-m-d H:i:s.uP';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}[+\-]\d{4}$/', $datetime)) {
            return 'Y-m-d H:i:sO';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.(\d{1,6})[+\-]\d{4}$/', $datetime, $matches)) {
            return strlen($matches[1]) <= 3 ? 'Y-m-d H:i:s.vO' : 'Y-m-d H:i:s.uO';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $datetime)) {
            return 'Y-m-d\TH:i:s';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.(\d{1,6})$/', $datetime, $matches)) {
            return strlen($matches[1]) <= 3 ? 'Y-m-d\TH:i:s.v' : 'Y-m-d\TH:i:s.u';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $datetime)) {
            return 'Y-m-d\TH:i:s\Z';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.(\d{1,6})Z$/', $datetime, $matches)) {
            return strlen($matches[1]) <= 3 ? 'Y-m-d\TH:i:s.v\Z' : 'Y-m-d\TH:i:s.u\Z';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+\-]\d{2}:\d{2}$/', $datetime)) {
            return 'Y-m-d\TH:i:sP';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.(\d{1,6})[+\-]\d{2}:\d{2}$/', $datetime, $matches)) {
            return strlen($matches[1]) <= 3 ? 'Y-m-d\TH:i:s.vP' : 'Y-m-d\TH:i:s.uP';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+\-]\d{4}$/', $datetime)) {
            return 'Y-m-d\TH:i:sO';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.(\d{1,6})[+\-]\d{4}$/', $datetime, $matches)) {
            return strlen($matches[1]) <= 3 ? 'Y-m-d\TH:i:s.vO' : 'Y-m-d\TH:i:s.uO';
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
            $minutes = $this->minutesBetweenTwoDates($startDate, $endDate);

            if ($minutes > $intervalUnit) {
                return true;
            }

            return (
                $minutes === $intervalUnit
                && 0 === $interval->invert
                && ($interval->s > 0 || $interval->f > 0)
            );
        }

        if (self::TIME_UNIT_HOURS == $timeUnit) {
            $hours = $this->hoursBetweenTwoDates($startDate, $endDate);

            if ($hours > $intervalUnit) {
                return true;
            }

            return (
                $hours === $intervalUnit
                && 0 === $interval->invert
                && ($interval->i > 0 || $interval->s > 0 || $interval->f > 0)
            );
        }

        if (self::TIME_UNIT_DAYS == $timeUnit) {
            $days = (int)$this->daysBetweenTwoDates($startDate, $endDate);

            if ($days > $intervalUnit) {
                return true;
            }

            return (
                $days === $intervalUnit
                && 0 === $interval->invert
                && ($interval->h > 0 || $interval->i > 0 || $interval->s > 0 || $interval->f > 0)
            );
        }

        if (self::TIME_UNIT_WEEKS == $timeUnit) {
            $totalDays = (int)$this->daysBetweenTwoDates($startDate, $endDate);
            $weeks     = intdiv($totalDays, 7);

            if ($weeks > $intervalUnit) {
                return true;
            }

            $remainingDays = abs($totalDays % 7);

            return (
                $weeks === $intervalUnit
                && 0 === $interval->invert
                && (
                    $remainingDays > 0
                    || $interval->h > 0
                    || $interval->i > 0
                    || $interval->s > 0
                    || $interval->f > 0
                )
            );
        }

        if (self::TIME_UNIT_MONTHS == $timeUnit) {
            if ($interval->invert === 1) {
                return false;
            }

            $monthsDiff   = ($interval->y * 12) + $interval->m;
            $hasRemainder = $interval->d > 0
                || $interval->h > 0
                || $interval->i > 0
                || $interval->s > 0
                || $interval->f > 0;

            if ($monthsDiff > $intervalUnit) {
                return true;
            }

            if ($monthsDiff === $intervalUnit) {
                return $hasRemainder;
            }

            return false;
        }

        if (self::TIME_UNIT_YEARS == $timeUnit) {
            if ($interval->invert === 1) {
                return false;
            }

            $yearsDiff    = $this->yearsBetweenTwoDates($startDate, $endDate);
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

    public function monthFromYearAndWeek(int $year, int $week, string $day = self::DAY_MONDAY): int
    {
        try {
            // Create \DateTime for Monday of the specified week
            $date = \DateTime::createFromFormat('o-W', sprintf('%d-%02d', $year, $week));

            // If $day is 'most' calculate which month has most days in this week
            if (strtolower($day) === self::DAY_MOST) {
                return $this->getMonthWithMostDays($date);
            }

            // Otherwise, use the specific day as before
            $daysToAdd = match (strtolower($day)) {
                self::DAY_MONDAY    => 0,
                self::DAY_TUESDAY   => 1,
                self::DAY_WEDNESDAY => 2,
                self::DAY_THURSDAY  => 3,
                self::DAY_FRIDAY    => 4,
                self::DAY_SATURDAY  => 5,
                self::DAY_SUNDAY    => 6,
                default             => throw new \InvalidArgumentException('Invalid day name')
            };

            $date->modify("+{$daysToAdd} days");

            return (int)$date->format('n');
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Invalid year ({$year}) or week number ({$week})", 0, $e);
        }
    }

    private function getMonthWithMostDays(\DateTime $startDate): int
    {
        $monthCount  = [];
        $currentDate = clone $startDate;

        // Count days in each month for all 7 days of the week
        for ($i = 0; $i < 7; $i++) {
            $month = (int)$currentDate->format('n');

            if (!isset($monthCount[$month])) {
                $monthCount[$month] = 0;
            }

            $monthCount[$month]++;
            $currentDate->modify('+1 day');
        }

        // Find the month with the maximum number of days
        $maxDays           = 0;
        $monthWithMostDays = 0;

        foreach ($monthCount as $month => $days) {
            if ($days > $maxDays) {
                $maxDays           = $days;
                $monthWithMostDays = $month;
            }
        }

        return $monthWithMostDays;
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
                $weekStartDate = ('1' === $date->format('N')) ? $date : $date->modify('previous Monday');
                if ($weekStartDate < $start) {
                    $weekStartDate = $start;
                }

                $weekEndDate = ('7' === $date->format('N')) ? $date : $date->modify('next Sunday');
                if ($weekEndDate > $end) {
                    $weekEndDate = $end;
                }

                $weeks[$weekYear] = [
                    'week'           => $week,
                    'year'           => $year,
                    'firstDayOfWeek' => $weekStartDate->format('Y-m-d'),
                    'lastDayOfWeek'  => $weekEndDate->format('Y-m-d'),
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
     *   eg: 30s => 30, 15m => 900, 1h => 3600, 2d => 172800, 3w => 1814400, 4mo => 10368000, 5y => 157680000
     */
    public function convertHumanTimeToSeconds(string $timeStr): int
    {
        $timeUnits = [
            's'  => 1,             // 1 second = 1 second
            'm'  => 60,            // 1 minute = 60 seconds
            'h'  => 3600,          // 1 hour = 3600 seconds
            'd'  => 86400,         // 1 day = 86400 seconds
            'w'  => 604800,        // 1 week = 604800 seconds
            'mo' => 2592000,       // 1 month (30 days) = 2592000 seconds
            'y'  => 31536000       // 1 year (365 days) = 31536000 seconds
        ];

        $timeStr = strtolower(trim($timeStr));

        if (!preg_match('/^(\d+)\s*([a-z]+)$/', $timeStr, $matches)) {
            return 0;
        }

        $number = (int)$matches[1];
        $unit   = $matches[2];

        if (!array_key_exists($unit, $timeUnits)) {
            return 0;
        }

        return $number * $timeUnits[$unit];
    }

    /**
     * Check if $date is between (today - $pastDays) 00:00:00 and (today + $futureDays) 23:59:59, inclusive
     */
    public function inDaysRange(\DateTimeInterface $date, int $pastDays, int $futureDays): bool
    {
        $now        = new \DateTimeImmutable();
        $startToday = $now->setTime(0, 0, 0);
        $endToday   = $now->setTime(23, 59, 59);

        $timestamp = $date->getTimestamp();
        $lower     = $startToday->modify("-{$pastDays} days")->getTimestamp();
        $upper     = $endToday->modify("+{$futureDays} days")->getTimestamp();

        return $timestamp >= $lower && $timestamp <= $upper;
    }
}
