<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Response;
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

    public function testFake(): void
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
    }

    public function testBlackholeRoutes(): void
    {
        $this->client->request('GET', '/.env');
        $this->assertEquals(Response::HTTP_PERMANENTLY_REDIRECT, $this->client->getResponse()->getStatusCode());
    }
}
