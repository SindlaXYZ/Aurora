<?php

namespace Sindla\Bundle\auroraBundle\Utils\Helper;

use GeoIp2\Database\Reader;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

/**
 * Debug: php bin/console debug:container aurora.helper
 */
class Helper
{
    private Container $container;

    public function __construct(Container $Container)
    {
        $this->container = $Container;
    }

    /**
     * Check if a key exists in a multidimensional array
     */
    public function arrayMultidimensionalKeyExists($key, array $array): bool
    {
        if (array_key_exists($key, $array)) {
            return true;
        } else {
            foreach ($array as $nested) {
                if (is_array($nested) && $this->arrayMultidimensionalKeyExists($key, $nested)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Convert a nested array into dot path array
     */
    public function arrayToFlattenedDotPath(array $array, $prepend = ''): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $results = array_merge($results, $this->arrayToFlattenedDotPath($value, $prepend . "{$key}" . '.'));
            } else {
                $results[$prepend . $key] = $value;
            }
        }

        return $results;
    }

    /**
     * Recursive ksort
     */
    public function ksortRecursive(array $array): array
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
