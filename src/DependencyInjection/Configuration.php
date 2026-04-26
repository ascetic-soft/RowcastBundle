<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
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
                ->arrayNode('profiler')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->info('Requires ascetic-soft/rowcast-profiler. When true, wraps Connection with ConnectionProfiler and optionally registers the web profiler panel.')
                        ->end()
                        ->booleanNode('collect_params')
                            ->defaultTrue()
                        ->end()
                        ->floatNode('slow_query_threshold_ms')
                            ->defaultValue(50.0)
                            ->min(0.0)
                        ->end()
                        ->integerNode('max_queries')
                            ->defaultValue(500)
                            ->min(0)
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
