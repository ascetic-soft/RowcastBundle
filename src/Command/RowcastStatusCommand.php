<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastBundle\Command;

use AsceticSoft\RowcastSchema\Cli\TableIgnoreMatcher;
use AsceticSoft\RowcastSchema\Diff\SchemaDiffer;
use AsceticSoft\RowcastSchema\Introspector\IntrospectorFactory;
use AsceticSoft\RowcastSchema\Migration\MigrationLoader;
use AsceticSoft\RowcastSchema\Migration\MigrationRepositoryInterface;
use AsceticSoft\RowcastSchema\Parser\SchemaParserInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'rowcast:status', description: 'Show migration and schema status')]
final class RowcastStatusCommand extends Command
{
    public function __construct(
        private readonly SchemaParserInterface $parser,
        private readonly IntrospectorFactory $introspectorFactory,
        private readonly SchemaDiffer $differ,
        private readonly MigrationLoader $loader,
        private readonly MigrationRepositoryInterface $repository,
        private readonly TableIgnoreMatcher $tableIgnoreMatcher,
        private readonly \PDO $pdo,
        private readonly string $schemaPath,
        private readonly string $migrationsPath,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->repository->ensureTable();

        $all = array_keys($this->loader->load($this->migrationsPath));
        $applied = $this->repository->getApplied();
        $appliedMap = array_flip($applied);
        $pending = array_values(array_filter($all, static fn (string $version): bool => !isset($appliedMap[$version])));

        $output->writeln(\sprintf('Applied: %d', \count($applied)));
        $output->writeln(\sprintf('Pending: %d', \count($pending)));

        if ($pending !== []) {
            $output->writeln('Pending migrations:');
            foreach ($pending as $version) {
                $output->writeln(' - ' . $version);
            }
        }

        $target = $this->tableIgnoreMatcher->filterSchema($this->parser->parse($this->schemaPath));
        $current = $this->tableIgnoreMatcher->filterSchema(
            $this->introspectorFactory->createForPdo($this->pdo)->introspect($this->pdo),
        );
        $diff = $this->differ->diff($current, $target);

        if ($diff === []) {
            $output->writeln('Schema is in sync.');
        } else {
            $output->writeln(\sprintf('Schema diff operations: %d', \count($diff)));
        }

        return Command::SUCCESS;
    }
}
