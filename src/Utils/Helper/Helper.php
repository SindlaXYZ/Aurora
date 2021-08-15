<?php

namespace Sindla\Bundle\auroraBundle\Utils\Helper;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use GeoIp2\Database\Reader;

/**
 * Debug: php bin/console debug:container aurora.helper
 */
class Helper
{
    private $container;

    public function __construct(Container $Container)
    {
        $this->container = $Container;
    }

    /**
     * Check if a key exists in a multidimensional array
     *
     * @param       $key
     * @param array $array
     * @return bool
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
     *
     * @param array  $array
     * @param string $prepend
     * @return array
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
}
