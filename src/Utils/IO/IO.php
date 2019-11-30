<?php

namespace Sindla\Bundle\BorealisBundle\Utils\IO;

class IO
{
    /**
     * Recursive create a directory
     *
     * @param   string $directory
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
     * @param   string  $str
     * @param   boolean $removeGivenDir
     * @return  boolean
     */
    public function recursiveDelete(string $str, bool $removeGivenDir = true)
    {
        if (is_file($str)) {
            return @unlink($str);

        } else if (is_dir($str)) {
            $scan = glob(rtrim($str, '/') . '/*');

            if (is_array($scan) AND count($scan) > 0) {
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

    public function dirIsEmpty(string $directory)
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
}