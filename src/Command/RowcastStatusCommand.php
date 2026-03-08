<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastBundle\Command;

use AsceticSoft\RowcastSchema\Cli\OperationDescriber;
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
use Symfony\Component\Console\Style\SymfonyStyle;

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
        private readonly OperationDescriber $operationDescriber,
        private readonly \PDO $pdo,
        private readonly string $schemaPath,
        private readonly string $migrationsPath,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Rowcast -- status');

        $this->repository->ensureTable();

        $all = array_keys($this->loader->load($this->migrationsPath));
        $applied = $this->repository->getApplied();
        $appliedMap = array_flip($applied);
        $pending = array_values(array_filter($all, static fn (string $version): bool => !isset($appliedMap[$version])));

        $io->text('Migrations:');
        $io->newLine();
        if ($all === []) {
            $io->writeln('    <comment>(no migration files found)</comment>');
        } else {
            foreach ($all as $version) {
                if (isset($appliedMap[$version])) {
                    $io->writeln(\sprintf('    <fg=green>[OK]</> %s  applied', $version));
                    continue;
                }

                $io->writeln(\sprintf('    <comment>[..]</comment> %s  pending', $version));
            }
        }
        $io->newLine();
        $io->info(\sprintf('Applied: %d | Pending: %d', \count($applied), \count($pending)));

        $target = $this->tableIgnoreMatcher->filterSchema($this->parser->parse($this->schemaPath));
        $current = $this->tableIgnoreMatcher->filterSchema(
            $this->introspectorFactory->createForPdo($this->pdo)->introspect($this->pdo),
        );
        $diff = $this->differ->diff($current, $target);

        if ($diff === []) {
            $io->success('Schema: in sync.');
        } else {
            $io->warning(\sprintf(
                'Schema: %d %s detected.',
                \count($diff),
                \count($diff) === 1 ? 'operation' : 'operations',
            ));
            $io->newLine();
            foreach ($diff as $operation) {
                $io->writeln('    ' . $this->operationDescriber->describe($operation));
            }
            $io->newLine();
            $io->info('Summary: ' . $this->operationDescriber->describeSummary($diff));
        }

        return Command::SUCCESS;
    }
}
