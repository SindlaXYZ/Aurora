<?php

namespace Sindla\Bundle\AuroraBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode    = $treeBuilder->root('aurora');

        $rootNode->children()
            ->scalarNode('root')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('tmp')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('resources')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('locale')->isRequired()->cannotBeEmpty()->end()
            ->arrayNode('locales')->scalarPrototype()->isRequired()->end()->end()
            ->scalarNode('bundle')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('maxmind_license_key')->isRequired()->cannotBeEmpty()->end()
        ->end();

        return $treeBuilder;
    }
}