<?php

namespace Sindla\Bundle\AuroraBundle\Tests;

// PHPUnit

// Symfony
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RequirementsTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testEnvironments()
    {
        $this->assertEquals('test', $_ENV['APP_ENV']);
    }
}