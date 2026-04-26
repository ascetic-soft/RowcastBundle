<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastBundle\DependencyInjection;

use AsceticSoft\Rowcast\Connection;
use AsceticSoft\Rowcast\ConnectionInterface;
use AsceticSoft\Rowcast\DataMapper;
use AsceticSoft\RowcastBundle\Command\RowcastDiffCommand;
use AsceticSoft\RowcastBundle\Command\RowcastMakeCommand;
use AsceticSoft\RowcastBundle\Command\RowcastMigrateCommand;
use AsceticSoft\RowcastBundle\Command\RowcastRollbackCommand;
use AsceticSoft\RowcastBundle\Command\RowcastStatusCommand;
use AsceticSoft\RowcastSchema\Cli\OperationDescriber;
use AsceticSoft\RowcastBundle\Factory\SchemaParserFactory;
use AsceticSoft\RowcastSchema\Cli\TableIgnoreMatcher;
use AsceticSoft\RowcastSchema\Diff\SchemaDiffer;
use AsceticSoft\RowcastSchema\Introspector\IntrospectorFactory;
use AsceticSoft\RowcastSchema\Migration\DatabaseMigrationRepository;
use AsceticSoft\RowcastSchema\Migration\MigrationGenerator;
use AsceticSoft\RowcastSchema\Migration\MigrationLoader;
use AsceticSoft\RowcastSchema\Migration\MigrationRepositoryInterface;
use AsceticSoft\RowcastSchema\Migration\MigrationRunner;
use AsceticSoft\RowcastSchema\Parser\SchemaParserInterface;
use AsceticSoft\RowcastSchema\Platform\PlatformFactory;
use AsceticSoft\RowcastSchema\Platform\PlatformInterface;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class RowcastExtension extends ConfigurableExtension
{
    public function getConfiguration(array $config, ContainerBuilder $container): ?ConfigurationInterface
    {
        return new Configuration();
    }

    /**
     * @param array{
     *     connection: array{
     *         dsn: string,
     *         username: ?string,
     *         password: ?string,
     *         options: array<mixed>,
     *         nest_transactions: bool
     *     },
     *     schema: array{
     *         path: string,
     *         migrations_path: string,
     *         migration_table: string,
     *         ignore_tables: array<int, string>
     *     },
     *     profiler: array{
     *         enabled: bool,
     *         collect_params: bool,
     *         slow_query_threshold_ms: float,
     *         max_queries: int
     *     }
     * } $mergedConfig
     */
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        // ConfigurableExtension already validates and merges config.
        // Reprocessing here breaks nested options (connection/schema).
        $config = $mergedConfig;

        $container->setParameter('rowcast.schema.path', $config['schema']['path']);
        $container->setParameter('rowcast.schema.migrations_path', $config['schema']['migrations_path']);
        $container->setParameter('rowcast.schema.migration_table', $config['schema']['migration_table']);
        $container->setParameter('rowcast.schema.ignore_tables', $config['schema']['ignore_tables']);

        $container->register(Connection::class)
            ->setFactory([Connection::class, 'create'])
            ->setArguments([
                $config['connection']['dsn'],
                $config['connection']['username'],
                $config['connection']['password'],
                $config['connection']['options'],
                $config['connection']['nest_transactions'],
            ]);
        $container->setAlias(ConnectionInterface::class, Connection::class);

        $container->register(DataMapper::class)
            ->setAutowired(true)
            ->setAutoconfigured(true);

        $container->register('rowcast.pdo')
            ->setClass(\PDO::class)
            ->setFactory([new Reference(Connection::class), 'getPdo']);

        $this->registerProfilerIfEnabled($config, $container);

        if (!$this->isRowcastSchemaAvailable()) {
            return;
        }

        $container->register(SchemaParserFactory::class);
        $container->register(SchemaParserInterface::class)
            ->setFactory([new Reference(SchemaParserFactory::class), 'create'])
            ->setArguments(['%rowcast.schema.path%']);

        $container->register(SchemaDiffer::class);
        $container->register(MigrationGenerator::class);
        $container->register(MigrationLoader::class);
        $container->register(IntrospectorFactory::class);
        $container->register(PlatformFactory::class);
        $container->register(PlatformInterface::class)
            ->setFactory([new Reference(PlatformFactory::class), 'createForPdo'])
            ->setArguments([new Reference('rowcast.pdo')]);
        $container->register(TableIgnoreMatcher::class)
            ->setArguments([
                '%rowcast.schema.ignore_tables%',
                '%rowcast.schema.migration_table%',
            ]);
        $container->register(OperationDescriber::class);
        $container->register(DatabaseMigrationRepository::class)
            ->setArguments([
                new Reference('rowcast.pdo'),
                '%rowcast.schema.migration_table%',
            ]);
        $container->setAlias(MigrationRepositoryInterface::class, DatabaseMigrationRepository::class);

        $container->register(MigrationRunner::class)
            ->setArguments([
                new Reference('rowcast.pdo'),
                new Reference(MigrationLoader::class),
                new Reference(MigrationRepositoryInterface::class),
                new Reference(PlatformInterface::class),
            ]);

        if (!$this->isConsoleAvailable()) {
            return;
        }

        $container->register(RowcastDiffCommand::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setArguments([
                new Reference(SchemaParserInterface::class),
                new Reference(IntrospectorFactory::class),
                new Reference(SchemaDiffer::class),
                new Reference(MigrationGenerator::class),
                new Reference(TableIgnoreMatcher::class),
                new Reference(OperationDescriber::class),
                new Reference('rowcast.pdo'),
                '%rowcast.schema.path%',
                '%rowcast.schema.migrations_path%',
            ])
            ->addTag('console.command');
        $container->register(RowcastMakeCommand::class)
            ->setArguments([
                new Reference(MigrationGenerator::class),
                '%rowcast.schema.migrations_path%',
            ])
            ->setAutoconfigured(true)
            ->addTag('console.command');
        $container->register(RowcastMigrateCommand::class)
            ->setArguments([
                new Reference(MigrationRunner::class),
                '%rowcast.schema.migrations_path%',
            ])
            ->setAutoconfigured(true)
            ->addTag('console.command');
        $container->register(RowcastRollbackCommand::class)
            ->setArguments([
                new Reference(MigrationRunner::class),
                '%rowcast.schema.migrations_path%',
            ])
            ->setAutoconfigured(true)
            ->addTag('console.command');
        $container->register(RowcastStatusCommand::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setArguments([
                new Reference(SchemaParserInterface::class),
                new Reference(IntrospectorFactory::class),
                new Reference(SchemaDiffer::class),
                new Reference(MigrationLoader::class),
                new Reference(MigrationRepositoryInterface::class),
                new Reference(TableIgnoreMatcher::class),
                new Reference(OperationDescriber::class),
                new Reference('rowcast.pdo'),
                '%rowcast.schema.path%',
                '%rowcast.schema.migrations_path%',
            ])
            ->addTag('console.command');
    }

    protected function isRowcastSchemaAvailable(): bool
    {
        return class_exists(MigrationRunner::class)
            && class_exists(SchemaDiffer::class)
            && interface_exists(SchemaParserInterface::class);
    }

    protected function isConsoleAvailable(): bool
    {
        return class_exists(Command::class);
    }

    /**
     * @param array{
     *     profiler: array{
     *         enabled: bool,
     *         collect_params: bool,
     *         slow_query_threshold_ms: float,
     *         max_queries: int
     *     }
     * } $config
     */
    private function registerProfilerIfEnabled(array $config, ContainerBuilder $container): void
    {
        $profilerConfig = $config['profiler'];

        if (!$profilerConfig['enabled'] || !class_exists(\AsceticSoft\RowcastProfiler\ConnectionProfiler::class)) {
            return;
        }

        $storeClass = \AsceticSoft\RowcastProfiler\InMemoryQueryProfileStore::class;
        $sanitizerClass = \AsceticSoft\RowcastProfiler\DefaultParameterSanitizer::class;
        $classifierClass = \AsceticSoft\RowcastProfiler\SqlClassifier::class;
        $profilerClass = \AsceticSoft\RowcastProfiler\RowcastProfiler::class;
        $connectionProfilerClass = \AsceticSoft\RowcastProfiler\ConnectionProfiler::class;

        $container->register($storeClass)
            ->setArguments([$profilerConfig['max_queries']])
            ->addTag('kernel.reset', ['method' => 'reset']);

        $container->register($sanitizerClass);

        $container->register($classifierClass);

        $container->register($profilerClass)
            ->setArguments([
                new Reference($storeClass),
                new Reference($sanitizerClass),
                new Reference($classifierClass),
                $profilerConfig['slow_query_threshold_ms'],
                $profilerConfig['collect_params'],
            ]);

        $container->register($connectionProfilerClass)
            ->setDecoratedService(Connection::class)
            ->setArguments([
                new Reference('.inner'),
                new Reference($profilerClass),
            ]);

        if (!class_exists(\Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector::class)) {
            return;
        }

        $collectorClass = \AsceticSoft\RowcastBundle\DataCollector\RowcastDataCollector::class;

        $container->register($collectorClass)
            ->setArguments([new Reference($storeClass)])
            ->addTag('data_collector', [
                'id' => 'rowcast',
                'template' => '@Rowcast/Collector/rowcast.html.twig',
                'priority' => 255,
            ]);
    }
}
