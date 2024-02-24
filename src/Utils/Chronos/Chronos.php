<?php

namespace Sindla\Bundle\AuroraBundle\Utils\Chronos;

class Chronos
{
    const TIME_UNIT_SECONDS = 1;
    const TIME_UNIT_MINUTES = 2;
    const TIME_UNIT_HOURS   = 3;
    const TIME_UNIT_DAYS    = 4;
    const TIME_UNIT_WEEKS   = 5;
    const TIME_UNIT_MONTHS  = 6;
    const TIME_UNIT_YEARS   = 7;

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

        $interval = $startDate->diff($endDate);
        $hours    = (int)$interval->format('%r%h');
        $minutes  = (int)$interval->format('%r%i');

        return (($hours * 60) + $minutes);
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
     * Return months number between two dates
     *
     * @param mixed $startDate
     * @param mixed $endDate
     * @return  integer
     */
    public function monthsBetweenTwoDates($startDate, $endDate): int
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

        return (($endDate->diff($startDate)->y * 12) + ($startDate->diff($endDate)->m));
    }

    /**
     * Return years number between two dates
     *
     * @param mixed $startDate
     * @param mixed $endDate
     * @return  integer
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
}
