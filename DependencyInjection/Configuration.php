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
                ->arrayNode('sections')
                    ->prototype('array')
                        ->children()
                            ->arrayNode('entities')
                                ->prototype('array')
                                    ->beforeNormalization()
                                        ->ifString()
                                        ->then(function($v) { return array('class' => $v); })
                                    ->end()
                                    ->children()
                                        ->scalarNode('form')->end()
                                        ->scalarNode('label')->end()
                                        ->scalarNode('class')->isRequired()->end()
                                        ->scalarNode('trash_attr')->defaultValue('removed')->end()
                                        ->booleanNode('uploadable')->defaultValue(false)->end()
                                        ->arrayNode('templates')
                                            ->children()
                                                ->scalarNode('form')->end()
                                                ->scalarNode('list')->end()
                                                ->scalarNode('list_item')->end()
                                                ->scalarNode('show')->end()
                                            ->end()
                                        ->end()
                                        ->arrayNode('list')
                                            ->beforeNormalization()
                                                ->ifString()
                                                ->then(function($v) { return array($v); })
                                            ->end()
                                            ->prototype('scalar')->end()
                                        ->end()
                                        ->arrayNode('filters')
                                            ->beforeNormalization()
                                                ->ifString()
                                                ->then(function($v) { return array($v); })
                                            ->end()
                                            ->prototype('scalar')->end()
                                        ->end()
                                        ->arrayNode('relations')
                                            ->prototype('array')
                                                ->children()
                                                    ->booleanNode('enabled')->defaultValue(true)->end()
                                                    ->arrayNode('bridges')
                                                        ->beforeNormalization()
                                                            ->ifString()
                                                            ->then(function($v) { return array($v); })
                                                        ->end()
                                                        ->prototype('scalar')->end()
                                                    ->end()
                                                ->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('config')
                                ->children()
                                    ->scalarNode('label')->end()
                                    ->scalarNode('dashboard')->end()
                                    ->arrayNode('roles')
                                        ->prototype('scalar')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('options')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('page_limit')->defaultValue(30)->end()
                        ->scalarNode('page_range')->defaultValue(7)->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
