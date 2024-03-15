<?php declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/RequirementsTest.php --no-coverage
 */
class RequirementsTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testEnvironments(): void
    {
        $this->assertEquals('test', $_ENV['APP_ENV']);
    }
}
