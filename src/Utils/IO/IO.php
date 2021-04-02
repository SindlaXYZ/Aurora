<?php

namespace Sindla\Bundle\AuroraBundle\Utils\IO;

use Sindla\Bundle\AuroraBundle\Utils\Chronos\Chronos;

class IO
{
    const TIME_UNIT_SECONDS = Chronos::TIME_UNIT_SECONDS;
    const TIME_UNIT_MINUTES = Chronos::TIME_UNIT_MINUTES;
    const TIME_UNIT_HOURS   = Chronos::TIME_UNIT_HOURS;
    const TIME_UNIT_DAYS    = Chronos::TIME_UNIT_DAYS;
    const TIME_UNIT_WEEKS   = Chronos::TIME_UNIT_WEEKS;
    const TIME_UNIT_MONTHS  = Chronos::TIME_UNIT_MONTHS;
    const TIME_UNIT_YEARS   = Chronos::TIME_UNIT_YEARS;

    /**
     * Recursive create a directory
     *
     * @param string $directory
     * @return  boolean
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
     *
     * @param string  $str
     * @param boolean $removeGivenDir
     * @return  boolean
     */
    public function recursiveDelete(string $str, bool $removeGivenDir = true)
    {
        if (is_file($str)) {
            return @unlink($str);

        } else if (is_dir($str)) {
            $scan = glob(rtrim($str, '/') . '/*');

            if (is_array($scan) && count($scan) > 0) {
                foreach ($scan as $index => $path) {
                    $this->recursiveDelete($path);
                }
            }

            if ($removeGivenDir === true) {
                return @rmdir($str);
            } else {
                return true;
            }
        }
    }

    public function dirIsEmpty(string $directory): bool
    {
        $handle = opendir($directory);
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != "..") {
                closedir($handle);
                return false;
            }
        }
        closedir($handle);
        return true;
    }

    public function fileIsOlderThan(string $file, int $timeUnit, int $timeUnitType): bool
    {
        /** @var Cronos $Chronos */
        $Chronos = new Chronos();

        $lastModifiedTimestamp = filemtime($file);
        $startDate             = new \DateTime("@{$lastModifiedTimestamp}");
        $endDate               = new \DateTime();

        return $Chronos->diffIsHigherThan($startDate, $endDate, $timeUnit, $timeUnitType);
    }
}