<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests;

use Sindla\Bundle\AuroraBundle\Controller\CustomExceptionController;
use Sindla\Bundle\AuroraBundle\Utils\AuroraClient\AuroraClient;
use Sindla\Bundle\AuroraBundle\Utils\AuroraTwig\UtilityExtension;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The bundle in the kernel of the host application (routes, controllers, commands and services wired by the bundle)
 *
 * clear; php vendor/phpunit/phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/AuroraBundleKernelTest.php --no-coverage
 */
class AuroraBundleKernelTest extends WebTestCase
{
    public function testTheCompiledCssAndJsRouteReachesItsController(): void
    {
        $client = static::createClient();
        $client->request('GET', '/aurora/compiled/aurora-missing-file.css');

        // "aurora.controller.compiled:cssJsFiles" (the single colon notation, removed in Symfony 6) answered a 500
        $this->assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $this->assertSame('/* File aurora-missing-file.css not found */', $client->getResponse()->getContent());
    }

    public function testTheOfflinePageIsRendered(): void
    {
        $client = static::createClient();
        $client->request('GET', '/pwa-offline');

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('<title>No internet!</title>', (string)$client->getResponse()->getContent());
    }

    public function testEveryCommandIsRegisteredOnceUnderItsNameAndAliases(): void
    {
        $application = new Application(static::bootKernel());
        $names       = array_values(array_filter(array_keys($application->all()), static fn(string $name): bool => str_starts_with($name, 'aurora:')));
        sort($names);

        // Every command used to be registered under a "name|alias" name too, and "aurora:composer" was an alias of "aurora:lazy.entity"
        $this->assertSame(['aurora:cloudflare:r2', 'aurora:composer', 'aurora:lazy.entity', 'aurora:php-unit', 'aurora:phpunit', 'aurora:test'], $names);
        $this->assertSame('Composer update command', $application->find('aurora:composer')->getDescription());
        $this->assertSame([], $application->find('aurora:composer')->getAliases());
        $this->assertSame([], $application->find('aurora:lazy.entity')->getAliases());
        $this->assertSame(['aurora:phpunit'], $application->find('aurora:php-unit')->getAliases());
    }

    public function testAControllerCanBeReferencedByItsClass(): void
    {
        self::bootKernel();

        // e.g. framework.error_controller: 'Sindla\Bundle\AuroraBundle\Controller\CustomExceptionController::handler'
        $controller = static::getContainer()->get('controller_resolver')->getController(
            new Request(attributes: ['_controller' => CustomExceptionController::class . '::handler'])
        );

        $this->assertIsArray($controller);
        $this->assertInstanceOf(CustomExceptionController::class, $controller[0]);
        $this->assertSame('handler', $controller[1]);
    }

    public function testThePublicServicesCanBeAutowiredByTheirClass(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->assertSame($container->get('aurora.client'), $container->get(AuroraClient::class));
        $this->assertSame($container->get('aurora.twig.utility'), $container->get(UtilityExtension::class));
    }
}
