<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Controller\PWAController;
use Sindla\Bundle\AuroraBundle\Utils\AuroraPWA\AuroraPWA;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class PWAControllerTest extends TestCase
{
    private const array PWA_METHODS = ['manifestJSON', 'browserConfig', 'mainJS', 'serviceWorkerJS', 'icon'];

    #[DataProvider('dataRequests')]
    public function testDispatchesByThePathNotByTheRequestUri(Request $request, string $expectedMethod): void
    {
        $pwa = $this->createMock(AuroraPWA::class);

        foreach (self::PWA_METHODS as $method) {
            $pwa
                ->expects($method === $expectedMethod ? $this->once() : $this->never())
                ->method($method)
                ->willReturn('manifestJSON' === $method ? new JsonResponse([$method]) : new Response($method));
        }

        $container = new Container();
        $container->set('aurora.pwa', $pwa);

        $controller = new PWAController($this->createStub(Environment::class));
        $controller->setContainer($container);

        // Used to answer "/pwa-sw.js?v42" (e.g. an asset version) with the favicon, and to require APCu (500 when not enabled)
        $this->assertSame(200, $controller->progressiveWebApplication($request, null, null)->getStatusCode());
    }

    public static function dataRequests(): iterable
    {
        yield 'manifest with a query string' => [Request::create('/manifest.json?v=2'), 'manifestJSON'];
        yield 'web manifest' => [Request::create('/manifest.webmanifest'), 'manifestJSON'];
        yield 'browser config with a query string' => [Request::create('/browserconfig.xml?x=1'), 'browserConfig'];
        yield 'main JS with an asset version' => [Request::create('/pwa-main.js?v42'), 'mainJS'];
        yield 'service worker with an asset version' => [Request::create('/pwa-sw.js?v42'), 'serviceWorkerJS'];
        yield 'service worker alias' => [Request::create('/sw.js'), 'serviceWorkerJS'];
        yield 'service worker under a base path' => [
            Request::create('/app/pwa-sw.js', server: ['SCRIPT_FILENAME' => '/srv/public/index.php', 'SCRIPT_NAME' => '/app/index.php']),
            'serviceWorkerJS',
        ];
        yield 'favicon with a query string' => [Request::create('/favicon.ico?v=3'), 'icon'];
        yield 'icon' => [Request::create('/android-icon-192x192.png'), 'icon'];
    }
}
