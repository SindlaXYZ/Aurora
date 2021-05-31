<?php

namespace Sindla\Bundle\AuroraBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration
 *
 * @doc https://symfony.com/doc/current/bundles/configuration.html
 *
 * @package Sindla\Bundle\AuroraBundle\DependencyInjection
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('aurora');

        /*
        $rootNode    = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->scalarNode('bundle')->isRequired()->cannotBeEmpty()->defaultValue('App')->end()
                //->scalarNode('root')->isRequired()->cannotBeEmpty()->defaultValue('%kernel.project_dir%')->end()
                ->scalarNode('tmp')->isRequired()->cannotBeEmpty()->defaultValue('%kernel.project_dir%/var/tmp')->end()
                ->scalarNode('resources')->isRequired()->cannotBeEmpty()->defaultValue('%kernel.project_dir%/var/resources/')->end()
                ->scalarNode('locale')->isRequired()->cannotBeEmpty()->defaultValue('en')->end()
                ->arrayNode('locales')->scalarPrototype()->end()->end()
                ->arrayNode('pwa')
                    ->children()
                        ->variableNode('app_name')->end()
                        ->variableNode('app_short_name')->end()
                        ->variableNode('app_description')->end()
                        ->variableNode('start_url')->end()
                        ->variableNode('display')->end()
                        ->variableNode('icons')->end()
                        ->variableNode('theme_color')->end()
                        ->variableNode('background_color')->end()
                        ->arrayNode('prevent_cache')->scalarPrototype()->end()->end()
                        ->arrayNode('external_cache')->scalarPrototype()->end()->end()
                    ->end()
                ->end()
            ->end();
        */

        return $treeBuilder;
    }
}