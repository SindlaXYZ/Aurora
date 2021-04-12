<?php

namespace Sindla\Bundle\AuroraBundle\Tests\Controller;

// PHPUnit
use PHPUnit\Framework\TestCase;

// Symfony
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Response;

// Vendor
use Sindla\Bundle\AuroraBundle\Tests\WebTestCaseMiddleware;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Controller/BlackHoleControllerTest.php --no-coverage
 */
class BlackHoleControllerTest extends WebTestCaseMiddleware
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testFake()
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
    }

    public function testBlackholeRoutes()
    {
        $this->client->request('GET', '/.env');
        $this->assertEquals(Response::HTTP_PERMANENTLY_REDIRECT, $this->client->getResponse()->getStatusCode());
    }
}