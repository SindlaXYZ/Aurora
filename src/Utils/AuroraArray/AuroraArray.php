<?php

namespace Sindla\Bundle\auroraBundle\Utils\AuroraArray;


class AuroraArray
{
    /**
     * Check if a key exists in a multidimensional array
     */
    public function multiDimensionalKeyExists($key, array $array): bool
    {
        if (array_key_exists($key, $array)) {
            return true;
        } else {
            foreach ($array as $nested) {
                if (is_array($nested) && $this->multiDimensionalKeyExists($key, $nested)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Convert a nested array into dot path array
     */
    public function toFlattenedDotPath(array $array, $prepend = ''): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $results = array_merge($results, $this->toFlattenedDotPath($value, $prepend . "{$key}" . '.'));
            } else {
                $results[$prepend . $key] = $value;
            }
        }

        return $results;
    }

    public function kSortRecursive(array $array): array
    {
        // call_user_func to avoid passed by reference issue with singleton instances
        $array = call_user_func(function (array $a) {
            ksort($a);
            return $a;
        }, $array);

        foreach ($array as $k => $v) {
            if (is_array($v)) {
                $array[$k] = $this->ksortRecursive($v);
            }
        }

        return $array;
    }
}
