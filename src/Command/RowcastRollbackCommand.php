<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastBundle\Command;

use AsceticSoft\RowcastSchema\Migration\MigrationRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'rowcast:rollback', description: 'Rollback RowcastSchema migrations')]
final class RowcastRollbackCommand extends Command
{
    public function __construct(
        private readonly MigrationRunner $runner,
        private readonly string $migrationsPath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('step', null, InputOption::VALUE_REQUIRED, 'How many applied migrations to rollback', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $step = (int) $input->getOption('step');
        $step = max(1, $step);

        $count = $this->runner->rollback($this->migrationsPath, $step);
        $output->writeln(\sprintf('Rolled back migrations: %d', $count));

        return Command::SUCCESS;
    }
}
