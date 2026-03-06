<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastBundle\Tests\DependencyInjection;

use AsceticSoft\Rowcast\Connection;
use AsceticSoft\Rowcast\ConnectionInterface;
use AsceticSoft\Rowcast\DataMapper;
use AsceticSoft\RowcastBundle\Command\RowcastDiffCommand;
use AsceticSoft\RowcastBundle\Command\RowcastMigrateCommand;
use AsceticSoft\RowcastBundle\Command\RowcastRollbackCommand;
use AsceticSoft\RowcastBundle\Command\RowcastStatusCommand;
use AsceticSoft\RowcastBundle\DependencyInjection\RowcastExtension;
use AsceticSoft\RowcastSchema\Migration\MigrationRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(RowcastExtension::class)]
final class RowcastExtensionTest extends TestCase
{
    public function test_it_registers_rowcast_and_schema_services_when_available(): void
    {
        $container = new ContainerBuilder();
        $extension = new RowcastExtension();

        $extension->load([
            [
                'connection' => [
                    'dsn' => 'sqlite::memory:',
                ],
            ],
        ], $container);

        self::assertTrue($container->hasDefinition(Connection::class));
        self::assertTrue($container->hasAlias(ConnectionInterface::class));
        self::assertTrue($container->hasDefinition(DataMapper::class));
        self::assertTrue($container->hasDefinition('rowcast.pdo'));
        self::assertSame(\PDO::class, $container->getDefinition('rowcast.pdo')->getClass());

        self::assertTrue($container->hasDefinition(MigrationRunner::class));
        self::assertTrue($container->hasDefinition(RowcastDiffCommand::class));
        self::assertTrue($container->hasDefinition(RowcastMigrateCommand::class));
        self::assertTrue($container->hasDefinition(RowcastRollbackCommand::class));
        self::assertTrue($container->hasDefinition(RowcastStatusCommand::class));
    }

    public function test_it_registers_only_core_services_when_schema_is_unavailable(): void
    {
        $container = new ContainerBuilder();
        $extension = new class extends RowcastExtension {
            protected function isRowcastSchemaAvailable(): bool
            {
                return false;
            }

            protected function isConsoleAvailable(): bool
            {
                return false;
            }
        };

        $extension->load([
            [
                'connection' => [
                    'dsn' => 'sqlite::memory:',
                ],
            ],
        ], $container);

        self::assertTrue($container->hasDefinition(Connection::class));
        self::assertTrue($container->hasAlias(ConnectionInterface::class));
        self::assertTrue($container->hasDefinition(DataMapper::class));
        self::assertTrue($container->hasDefinition('rowcast.pdo'));

        self::assertFalse($container->hasDefinition(MigrationRunner::class));
        self::assertFalse($container->hasDefinition(RowcastDiffCommand::class));
        self::assertFalse($container->hasDefinition(RowcastMigrateCommand::class));
        self::assertFalse($container->hasDefinition(RowcastRollbackCommand::class));
        self::assertFalse($container->hasDefinition(RowcastStatusCommand::class));
    }

    public function test_it_accepts_already_processed_merged_configuration(): void
    {
        $container = new ContainerBuilder();
        $extension = new class extends RowcastExtension {
            public function testLoadInternal(array $mergedConfig, ContainerBuilder $container): void
            {
                $this->loadInternal($mergedConfig, $container);
            }

            protected function isRowcastSchemaAvailable(): bool
            {
                return false;
            }

            protected function isConsoleAvailable(): bool
            {
                return false;
            }
        };

        $extension->testLoadInternal([
            'connection' => [
                'dsn' => 'sqlite::memory:',
                'username' => null,
                'password' => null,
                'options' => [],
                'nest_transactions' => false,
            ],
            'schema' => [
                'path' => '/tmp/schema.php',
                'migrations_path' => '/tmp/migrations',
                'migration_table' => '_rowcast_migrations',
                'ignore_tables' => [],
            ],
        ], $container);

        self::assertTrue($container->hasDefinition(Connection::class));
        self::assertSame(\PDO::class, $container->getDefinition('rowcast.pdo')->getClass());
    }
}
