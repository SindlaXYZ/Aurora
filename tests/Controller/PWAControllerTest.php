<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Response;
use Sindla\Bundle\AuroraBundle\Tests\WebTestCaseMiddleware;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Controller/PWAControllerTest.php --no-coverage
 */
class PWAControllerTest extends WebTestCaseMiddleware
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testFake(): void
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
    }

    public function testOffline(): void
    {
        $this->client->request('GET', '/pwa-offline');
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }
}
