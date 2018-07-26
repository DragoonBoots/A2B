<?php


namespace DragoonBoots\A2B\DependencyInjection;


use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration
 */
class Configuration implements ConfigurationInterface
{

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('a2b');

        // Keep the formatter off so the tree structure can be shown.
        // @formatter:off
        $rootNode
            ->children()
                ->arrayNode('mapper')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('db')
                          ->info('The database connection url to use for migration mapping data.  Defaults to a sqlite database in resources/data.')
                          ->isRequired()
                          ->cannotBeEmpty()
                          ->defaultValue('sqlite:///%kernel.project_dir%/resources/data/data_migration_map.sqlite')
                          ->end()
                    ->end()
                ->end()
            ->end()
        ;
        // @formatter:on

        return $treeBuilder;
    }
}
