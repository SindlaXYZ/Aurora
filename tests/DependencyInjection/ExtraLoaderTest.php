<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\DependencyInjection\ExtraLoader;
use Symfony\Component\Routing\Route;

class ExtraLoaderTest extends TestCase
{
    public function testLoadReturnsThePwaOfflineRoutes(): void
    {
        $routes = new ExtraLoader()->load('.', 'extra');

        $this->assertSame(
            ['aurora_aurora_pwa_offline' => '/aurora/pwa-offline', 'aurora_pwa_offline' => '/pwa-offline'],
            array_map(static fn(Route $route): string => $route->getPath(), $routes->all())
        );

        foreach ($routes as $route) {
            // The id of the controller service: "Sindla\...\PWAController::offline" was not a service
            $this->assertSame('aurora.controller.pwa::offline', $route->getDefault('_controller'));
        }
    }

    #[DataProvider('dataSupports')]
    public function testSupportsOnlyTheExtraType(?string $type, bool $expected): void
    {
        $this->assertSame($expected, new ExtraLoader()->supports('.', $type));
    }

    public static function dataSupports(): iterable
    {
        yield 'extra' => ['extra', true];
        yield 'yaml' => ['yaml', false];
        yield 'no type' => [null, false];
    }
}
