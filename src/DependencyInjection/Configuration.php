<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class Configuration implements ConfigurationInterface
{
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../config')
        );
        $loader->load('services.yml');
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('rowcast');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('connection')
                    ->isRequired()
                    ->children()
                        ->scalarNode('dsn')
                            ->isRequired()
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('username')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('password')
                            ->defaultNull()
                        ->end()
                        ->variableNode('options')
                            ->defaultValue([])
                        ->end()
                        ->booleanNode('nest_transactions')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('schema')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('path')
                            ->defaultValue('%kernel.project_dir%/database/schema.php')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('migrations_path')
                            ->defaultValue('%kernel.project_dir%/database/migrations')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('migration_table')
                            ->defaultValue('_rowcast_migrations')
                            ->cannotBeEmpty()
                        ->end()
                        ->arrayNode('ignore_tables')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
