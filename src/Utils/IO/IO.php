<?php

namespace Sindla\Bundle\AuroraBundle\Utils\IO;

use Sindla\Bundle\AuroraBundle\Utils\AuroraChronos\AuroraChronos;

class IO
{
    const int TIME_UNIT_SECONDS = AuroraChronos::TIME_UNIT_SECONDS;
    const int TIME_UNIT_MINUTES = AuroraChronos::TIME_UNIT_MINUTES;
    const int TIME_UNIT_HOURS   = AuroraChronos::TIME_UNIT_HOURS;
    const int TIME_UNIT_DAYS    = AuroraChronos::TIME_UNIT_DAYS;
    const int TIME_UNIT_WEEKS   = AuroraChronos::TIME_UNIT_WEEKS;
    const int TIME_UNIT_MONTHS  = AuroraChronos::TIME_UNIT_MONTHS;
    const int TIME_UNIT_YEARS   = AuroraChronos::TIME_UNIT_YEARS;

    /**
     * Recursive create a directory
     */
    public function recursiveCreateDirectory(string $directory): bool
    {
        if (!is_dir($directory)) {
            return (mkdir($directory, 0777, true)) ? true : false;
        } else {
            return true;
        }
    }

    /**
     * Recursive delete files/directories
     */
    public function recursiveDelete(string $str, bool $removeGivenDir = true): bool
    {
        if (is_file($str) || is_link($str)) {
            return @unlink($str);
        }

        if (is_dir($str)) {
            $items = @scandir($str);
            if (is_array($items)) {
                foreach (array_diff($items, ['.', '..']) as $item) {
                    $this->recursiveDelete($str . '/' . $item);
                }
            }

            if ($removeGivenDir) {
                return @rmdir($str);
            }

            return true;
        }

        return false;
    }

    public function dirIsEmpty(string $directory): bool
    {
        if (!is_dir($directory)) {
            return true;
        }

        if (!is_readable($directory)) {
            return false;
        }

        $handle = opendir($directory);

        if ($handle === false) {
            return false;
        }

        try {
            while (($entry = readdir($handle)) !== false) {
                if ($entry !== '.' && $entry !== '..') {
                    return false;
                }
            }
        } finally {
            closedir($handle);
        }

        return true;
    }

    public function fileIsOlderThan(string $file, int $timeUnit, int $timeUnitType): bool
    {
        if (!is_file($file)) {
            return false;
        }

        $lastModifiedTimestamp = @filemtime($file);
        if ($lastModifiedTimestamp === false) {
            return false;
        }

        $Chronos   = new AuroraChronos();
        $startDate = new \DateTime("@{$lastModifiedTimestamp}");
        $endDate   = new \DateTime();

        return $Chronos->diffIsHigherThan($startDate, $endDate, $timeUnit, $timeUnitType);
    }
}
