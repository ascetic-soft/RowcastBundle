<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastBundle\Command;

use AsceticSoft\RowcastSchema\Migration\MigrationRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'rowcast:migrate', description: 'Apply pending RowcastSchema migrations')]
final class RowcastMigrateCommand extends Command
{
    public function __construct(
        private readonly MigrationRunner $runner,
        private readonly string $migrationsPath,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->runner->migrate($this->migrationsPath);
        $output->writeln(\sprintf('Applied migrations: %d', $count));

        return Command::SUCCESS;
    }
}
