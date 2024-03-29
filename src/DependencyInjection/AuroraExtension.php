<?php

namespace Sindla\Bundle\AuroraBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Routing\RouteCollection;

// https://symfony.com/doc/current/bundles/extension.html

class AuroraExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator([__DIR__ . '/../Resources/config']));
        $loader->load('config.yaml');

        # https://symfony.com/doc/current/routing/custom_route_loader.html#creating-a-custom-loader
        # https://symfony.com/doc/current/routing/custom_route_loader.html#more-advanced-loaders
    }
}
