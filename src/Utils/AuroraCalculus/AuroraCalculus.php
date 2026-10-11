<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraCalculus;

use Sindla\Bundle\AuroraBundle\Utils\AuroraCalculus\Extension\Calculus2D;
use Sindla\Bundle\AuroraBundle\Utils\AuroraCalculus\Extension\Calculus3D;
use Sindla\Bundle\AuroraBundle\Utils\AuroraCalculus\Extension\CalculusGeo;
use Sindla\Bundle\AuroraBundle\Utils\AuroraCalculus\Extension\Graph;

// The traits are autoloaded (PSR-4): they used to be require()d here, a "Cannot redeclare trait" fatal error once a trait was loaded first,
// e.g. by the factory of the "aurora.calculus" service in a debug container (it include_once()s the traits of a class, then the class)
class AuroraCalculus
{
    use Calculus2D;
    use Calculus3D;
    use CalculusGeo;
    use Graph;

    /**
     * Check if a number is prime based on trial division
     *
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
     * Return percentage between two numbers.
     */
    public function percentageChange(float $newNumber, float $originalNumber): float
    {
        if (0.0 === $originalNumber) {
            return 0.0;
        }

        return (($newNumber - $originalNumber) / $originalNumber) * 100;
    }
}
