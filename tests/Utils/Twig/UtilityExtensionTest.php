<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\Twig;

// PHPUnit
use PHPUnit\Framework\TestCase;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/Twig/UtilityExtensionTest.php --no-coverage
 */
class UtilityExtensionTest extends TestCase
{
    /**
     * Fake test. Do not delete this, otherwise Bitbucket Pipeline will fail
     */
    public function testFake()
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
    }
}