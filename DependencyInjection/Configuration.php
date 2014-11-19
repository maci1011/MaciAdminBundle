<?php

namespace Maci\AdminBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('maci_admin');

        $rootNode
            ->children()
                ->arrayNode('entities')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('form')->end()
                            ->scalarNode('label')->end()
                            ->scalarNode('class')->isRequired()->end()
                            ->arrayNode('templates')
                                ->children()
                                    ->scalarNode('form')->end()
                                    ->scalarNode('list')->end()
                                    ->scalarNode('list_item')->end()
                                    ->scalarNode('show')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
