<?php

namespace Sindla\Bundle\AuroraBundle\Utils\Calculus;

use Sindla\Bundle\AuroraBundle\Utils\Calculus\Extension\Calculus2D;
use Sindla\Bundle\AuroraBundle\Utils\Calculus\Extension\Calculus3D;
use Sindla\Bundle\AuroraBundle\Utils\Calculus\Extension\CalculusGeo;
use Sindla\Bundle\AuroraBundle\Utils\Calculus\Extension\Graph;

require dirname(__FILE__) . '/Extension/Calculus2D.php';
require dirname(__FILE__) . '/Extension/Calculus3D.php';
require dirname(__FILE__) . '/Extension/Graph.php';
require dirname(__FILE__) . '/Extension/CalculusGeo.php';

class Calculus
{
    use Calculus2D;
    use Calculus3D;
    use CalculusGeo;
    use Graph;

    /**
     * Check if a number is prime based on trial division
     *
     * @param integer $number
     * @return  boolean
     * @docs    http://en.wikipedia.org/wiki/Prime_number
     * @docs    http://www.ideaflix.com/question/formula-to-calculate-distance-between-two-latitude-and-longitude-in-php/
     */
    public function isPrimeNumber(int $number): bool
    {
        if ($number <= 3) {
            return ($number > 1);
        } else if ($number % 2 === 0 || $number % 3 === 0) {
            return false;
        } else {
            for ($i = 5; $i * $i <= $number; $i += 6) {
                if ($number % $i === 0 || $number % ($i + 2) === 0) {
                    return false;
                }
            }
            return true;
        }
    }

    /**
     * Return percentage between two number
     *
     * @param $newNumber
     * @param $originalNumber
     * @return float|int
     */
    public function percentageChange($newNumber, $originalNumber)
    {
        return ((($newNumber - $originalNumber) / $originalNumber) * 100);
    }
}