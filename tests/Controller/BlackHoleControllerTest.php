<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Controller;

use Sindla\Bundle\AuroraBundle\Tests\WebTestCaseMiddleware;
use Symfony\Component\HttpFoundation\Response;

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
        $this->assertTrue(
            in_array($this->client->getResponse()->getStatusCode(), [Response::HTTP_PERMANENTLY_REDIRECT, Response::HTTP_NOT_FOUND])
        );
    }
}
