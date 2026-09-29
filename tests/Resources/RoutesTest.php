<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Resources;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\AuroraBundle;
use Sindla\Bundle\AuroraBundle\DependencyInjection\ExtraLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
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

    /**
     * "service::method" (or an invokable service): "aurora.controller.compiled:cssJsFiles" (the single colon notation, removed in
     * Symfony 6) answered a 500, and the "extra" routes referenced the controller by its class instead of its service id
     */
    public function testEveryControllerIsAMethodOfAServiceOfTheBundle(): void
    {
        $container = new ContainerBuilder(new ParameterBag(['kernel.environment' => 'test']));
        new AuroraBundle()->getContainerExtension()->load([], $container);

        foreach ($this->loadRoutes() as $name => $route) {
            $controller = (string)$route->getDefault('_controller');

            $this->assertMatchesRegularExpression('/^[a-z_.]+(::[A-Za-z]+)?$/', $controller, sprintf('The controller of the route "%s" is not a service id.', $name));

            [$id, $method] = explode('::', $controller) + [1 => '__invoke'];

            $this->assertTrue($container->hasDefinition($id), sprintf('The controller service "%s" of the route "%s" does not exist.', $id, $name));
            $this->assertTrue(
                method_exists($container->getDefinition($id)->getClass(), $method),
                sprintf('The controller "%s" of the route "%s" does not exist.', $controller, $name)
            );
        }
    }

    private function loadRoutes(): RouteCollection
    {
        $loader = new YamlFileLoader(new FileLocator(dirname(__DIR__, 2) . '/src/Resources/config/routes'));
        new LoaderResolver([$loader, new ExtraLoader()]);

        return $loader->load('routes.yaml');
    }
}
