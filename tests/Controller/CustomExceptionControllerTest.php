<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Controller\CustomExceptionController;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class CustomExceptionControllerTest extends TestCase
{
    #[DataProvider('dataExceptions')]
    public function testRendersTheErrorPageWithTheStatusCodeOfTheException(\Throwable $exception, int $expectedStatusCode): void
    {
        $loader = new FilesystemLoader();
        $loader->addPath(dirname(__DIR__, 2) . '/src/templates', 'Aurora');

        $container = new Container();
        $container->set('twig', new Environment($loader));

        $controller = new CustomExceptionController();
        $controller->setContainer($container);

        $response = $controller->handler(Request::create('/missing'), $exception);

        $this->assertSame($expectedStatusCode, $response->getStatusCode());
        $this->assertStringContainsString(sprintf('<title>[%d] ', $expectedStatusCode), $response->getContent());
        $this->assertStringContainsString(sprintf('<p>Error code %d</p>', $expectedStatusCode), $response->getContent());
    }

    public static function dataExceptions(): iterable
    {
        yield 'not found' => [new NotFoundHttpException(), 404];
        yield 'access denied' => [new AccessDeniedHttpException(), 403];
        yield 'not an HTTP exception' => [new \RuntimeException('Aurora'), 500];
    }
}
