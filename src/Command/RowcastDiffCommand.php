<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastBundle\Command;

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

#[AsCommand(name: 'rowcast:diff', description: 'Generate migration file from schema diff')]
final class RowcastDiffCommand extends Command
{
    public function __construct(
        private readonly SchemaParserInterface $parser,
        private readonly IntrospectorFactory $introspectorFactory,
        private readonly SchemaDiffer $differ,
        private readonly MigrationGenerator $generator,
        private readonly TableIgnoreMatcher $tableIgnoreMatcher,
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
        $target = $this->tableIgnoreMatcher->filterSchema($this->parser->parse($this->schemaPath));
        $current = $this->tableIgnoreMatcher->filterSchema(
            $this->introspectorFactory->createForPdo($this->pdo)->introspect($this->pdo),
        );
        $operations = $this->differ->diff($current, $target);

        if ((bool) $input->getOption('dry-run')) {
            if ($operations === []) {
                $output->writeln('No schema changes detected.');
                return Command::SUCCESS;
            }

            foreach ($operations as $operation) {
                $output->writeln($operation::class);
            }

            return Command::SUCCESS;
        }

        if ($operations === []) {
            $output->writeln('No schema changes detected. Migration file was not created.');
            return Command::SUCCESS;
        }

        $file = $this->generator->generate($operations, $this->migrationsPath);
        $output->writeln(\sprintf('Migration generated: %s', $file));

        return Command::SUCCESS;
    }
}
