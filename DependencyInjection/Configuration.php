<?php

namespace MuchoMasFacil\FileManagerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

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
    //arrayNode, scalarNode, variableNode

        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('mucho_mas_facil_file_manager');

        $rootNode
            ->children()
                ->arrayNode('options')
                ->useAttributeAsKey('name')
                ->prototype('array')->children()
                    ->scalarNode('upload_path_after_document_root')->end()
                    ->booleanNode('create_path_if_not_exist')->end()
                    ->booleanNode('replace_old_file')->end()
                    ->scalarNode('max_number_of_files')->end()
                    ->scalarNode('on_select_callback_function')->end()
                    ->scalarNode('size_limit')->end()
                    //->scalarNode('min_size_limit')->end()
                    //->scalarNode('max_connections')->end()
                    ->scalarNode('allowed_extensions')->end()
                    ->scalarNode('allowed_roles')->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
