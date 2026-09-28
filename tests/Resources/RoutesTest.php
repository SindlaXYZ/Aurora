<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Resources;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\DependencyInjection\ExtraLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Yaml\Parser;

/**
 * clear; php vendor/phpunit/phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Resources/RoutesTest.php --no-coverage
 */
#[RequiresMethod(Parser::class, 'parse')]
class RoutesTest extends TestCase
{
    public function testTheRoutesHaveNoLocale(): void
    {
        $routes = $this->loadRoutes();

        $this->assertGreaterThan(100, $routes->count());

        foreach ($routes as $name => $route) {
            // A list of paths is read by Symfony as localized paths: "_locale" used to be the index of the path ("0", "1", ...)
            $this->assertNull($route->getDefault('_locale'), sprintf('The route "%s" sets a locale.', $name));
        }
    }

    #[DataProvider('dataTheMatchedRequestHasNoLocale')]
    public function testTheMatchedRequestHasNoLocale(string $path, string $expectedRoute): void
    {
        $parameters = new UrlMatcher($this->loadRoutes(), new RequestContext())->match($path);

        $this->assertSame($expectedRoute, $parameters['_route']);
        // A sticky-locale subscriber saved "7" as the locale of the visitor whose browser requested "/apple-icon-57x57.png"
        $this->assertNull($parameters['_locale'] ?? null);
    }

    public static function dataTheMatchedRequestHasNoLocale(): array
    {
        return [
            ['/favicon.ico', 'aurora.controller.pwa.favicon'],
            ['/apple-icon-57x57.png', 'aurora.controller.pwa.favicon'],
            ['/pwa-sw.js', 'aurora.controller.pwa.favicon'],
            ['/pwa-offline', 'aurora.controller.pwa.offline'],
            ['/wp-login.php', 'aurora.controller.blackhole'],
            ['/.env', 'aurora.controller.blackhole'],
            ['/aurora/test', 'aurora.controller.test'],
        ];
    }

    private function loadRoutes(): RouteCollection
    {
        $loader = new YamlFileLoader(new FileLocator(dirname(__DIR__, 2) . '/src/Resources/config/routes'));
        new LoaderResolver([$loader, new ExtraLoader()]);

        return $loader->load('routes.yaml');
    }
}
