<?php

namespace Sindla\Bundle\AuroraBundle\DependencyInjection;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use Psr\Container\ContainerInterface;

// https://symfony.com/doc/current/routing/custom_route_loader.html#creating-a-custom-loader

class ExtraLoader extends Loader
{
    private bool $isLoaded = false;

    public function load($resource, string $type = null): RouteCollection
    {
        if (true === $this->isLoaded) {
            throw new \RuntimeException('Do not add the "extra" loader twice');
        }

        $customRoutes = [
            [
                'name'         => 'aurora_aurora_pwa_offline',
                'paths'        => [
                    '/aurora/pwa-offline'
                ],
                'defaults'     => [
                    '_controller' => 'Sindla\Bundle\AuroraBundle\Controller\PWAController::offline'
                ],
                'requirements' => [

                ]
            ],
            [
                'name'         => 'aurora_pwa_offline',
                'paths'        => [
                    '/pwa-offline'
                ],
                'defaults'     => [
                    '_controller' => 'Sindla\Bundle\AuroraBundle\Controller\PWAController::offline'
                ],
                'requirements' => [

                ]
            ]
        ];

        $routes = new RouteCollection();

        foreach ($customRoutes as $customRoute) {
            foreach ($customRoute['paths'] as $path) {
                $route = new Route($path, $customRoute['defaults'], $customRoute['requirements']);
                $routes->add($customRoute['name'], $route);
            }
        }

        $this->isLoaded = true;

        return $routes;
    }

    public function supports($resource, string $type = null): bool
    {
        return 'extra' === $type;
    }
}
