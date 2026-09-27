<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Controller\TestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * clear; php vendor/phpunit/phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Controller/TestControllerTest.php --no-coverage
 */
class TestControllerTest extends TestCase
{
    public function testDispatchesTheTestAction(): void
    {
        $controller = new TestController();
        $response   = $controller(Request::create('/aurora/test'));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('It works!', $response->getContent());
    }

    public function testQueryStringDoesNotSelectTheAction(): void
    {
        // "/aurora/test?/redirect" used to call AbstractController::redirect() with the raw request (Cookie header included)
        $controller = new TestController();
        $response   = $controller(Request::create('/aurora/test?/redirect', 'GET', [], ['PHPSESSID' => 'secret-session-id']));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('It works!', $response->getContent());
    }

    public function testUnknownActionIsNotFound(): void
    {
        $controller = new TestController();

        $this->expectException(NotFoundHttpException::class);

        $controller(Request::create('/aurora/test/__invoke'));
    }

    public function testServiceActionReturnsTheClientIp(): void
    {
        $originalServer = $_SERVER;
        unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);

        try {
            $controller = new TestController();
            $response   = $controller(Request::create('/aurora/test/service', 'GET', [], [], [], ['REMOTE_ADDR' => '198.51.100.7']));

            $this->assertSame('198.51.100.7', $response->getContent());
        } finally {
            $_SERVER = $originalServer;
        }
    }
}
