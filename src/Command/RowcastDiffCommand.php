<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastBundle\Command;

use AsceticSoft\RowcastSchema\Cli\OperationDescriber;
use AsceticSoft\RowcastSchema\Cli\TableIgnoreMatcher;
use AsceticSoft\RowcastSchema\Diff\SchemaDiffer;
use AsceticSoft\RowcastSchema\Introspector\IntrospectorFactory;
use AsceticSoft\RowcastSchema\Migration\MigrationGenerator;
use AsceticSoft\RowcastSchema\Parser\SchemaParserInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'rowcast:diff', description: 'Generate migration file from schema diff')]
final class RowcastDiffCommand extends Command
{
    public function __construct(
        private readonly SchemaParserInterface $parser,
        private readonly IntrospectorFactory $introspectorFactory,
        private readonly SchemaDiffer $differ,
        private readonly MigrationGenerator $generator,
        private readonly TableIgnoreMatcher $tableIgnoreMatcher,
        private readonly OperationDescriber $operationDescriber,
        private readonly \PDO $pdo,
        private readonly string $schemaPath,
        private readonly string $migrationsPath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print operations instead of creating migration file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isDryRun = (bool) $input->getOption('dry-run');
        $io = new SymfonyStyle($input, $output);
        $io->title($isDryRun ? 'Rowcast -- diff (dry-run)' : 'Rowcast -- diff');

        $target = $this->tableIgnoreMatcher->filterSchema($this->parser->parse($this->schemaPath));
        $current = $this->tableIgnoreMatcher->filterSchema(
            $this->introspectorFactory->createForPdo($this->pdo)->introspect($this->pdo),
        );
        $operations = $this->differ->diff($current, $target);

        if ($isDryRun) {
            if ($operations === []) {
                $io->success('No schema changes detected.');
                return Command::SUCCESS;
            }

            $io->info(\sprintf(
                'Detected %d %s:',
                \count($operations),
                \count($operations) === 1 ? 'operation' : 'operations',
            ));
            $io->newLine();
            foreach ($operations as $operation) {
                $io->writeln('    ' . $this->operationDescriber->describe($operation));
                foreach ($this->operationDescriber->describeDetails($operation) as $detail) {
                    $io->writeln('        ' . $detail);
                }
            }
            $io->newLine();
            $io->info('Summary: ' . $this->operationDescriber->describeSummary($operations));

            return Command::SUCCESS;
        }

        if ($operations === []) {
            $io->success('No schema changes detected. Migration file was not created.');
            return Command::SUCCESS;
        }

        $io->info(\sprintf(
            'Detected %d %s:',
            \count($operations),
            \count($operations) === 1 ? 'operation' : 'operations',
        ));
        $io->newLine();
        foreach ($operations as $operation) {
            $io->writeln('    ' . $this->operationDescriber->describe($operation));
        }
        $io->newLine();
        $io->info('Summary: ' . $this->operationDescriber->describeSummary($operations));

        $file = $this->generator->generate($operations, $this->migrationsPath);
        $io->newLine();
        $io->success(\sprintf('Migration generated: %s', pathinfo($file, \PATHINFO_FILENAME)));
        $io->writeln('    ' . $file);

        return Command::SUCCESS;
    }
}
