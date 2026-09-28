<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraIO;

use Sindla\Bundle\AuroraBundle\Utils\AuroraChronos\AuroraChronos;

class AuroraIO
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

        if (!is_dir($str)) {
            return false;
        }

        $items  = @scandir($str);
        $result = true;

        if ($items === false) {
            $result = false;
            $items  = [];
        }

        foreach (array_diff($items, ['.', '..']) as $item) {
            if (!$this->recursiveDelete($str . '/' . $item)) {
                $result = false;
            }
        }

        if ($removeGivenDir) {
            return $result && @rmdir($str);
        }

        return $result;
    }

    /**
     * Replace $destination with $source (a file of the same file system) atomically, with rename(): a reader never sees a partial file.
     *
     * The permissions, the owner and the group of the replaced file are kept: the new file has the umask and the owner of the running user
     * (e.g. root or a deployment account), so the users that read the replaced file (e.g. the PHP workers) could lose the access to it.
     * When they cannot be kept (e.g. a file of another user) or $destination is a symbolic link, the content is copied into the replaced
     * file instead (not atomic), like it used to be.
     *
     * @throws \RuntimeException When the file cannot be replaced ($source is kept)
     */
    public function replaceFile(string $source, string $destination): void
    {
        $replaced = is_file($destination) ? @stat($destination) : false;

        if (is_link($destination) || (false !== $replaced && !$this->applyOwnerAndPermissions($source, $replaced))) {
            if (!@copy($source, $destination)) {
                throw new \RuntimeException(sprintf('Cannot write the file "%s".', $destination));
            }

            unlink($source);

            return;
        }

        if (!@rename($source, $destination)) {
            throw new \RuntimeException(sprintf('Cannot write the file "%s".', $destination));
        }
    }

    /**
     * @param array<int|string, int> $stat The stat() of the file whose owner, group and permissions are applied
     */
    private function applyOwnerAndPermissions(string $file, array $stat): bool
    {
        if (false === $current = @stat($file)) {
            return false;
        }

        // Only a privileged user can change the owner: it is changed only when it differs
        return ($current['uid'] === $stat['uid'] || @chown($file, $stat['uid']))
            && ($current['gid'] === $stat['gid'] || @chgrp($file, $stat['gid']))
            && @chmod($file, $stat['mode'] & 0777);
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
