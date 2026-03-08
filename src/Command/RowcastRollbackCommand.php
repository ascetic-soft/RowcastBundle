<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastBundle\Command;

use AsceticSoft\RowcastSchema\Migration\MigrationRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
        $io = new SymfonyStyle($input, $output);

        $step = (int) $input->getOption('step');
        $step = max(1, $step);
        $io->title(\sprintf('Rowcast -- rollback (step: %d)', $step));

        $rolledBackVersions = [];
        $count = $this->runner->rollback(
            $this->migrationsPath,
            $step,
            static function (string $version) use (&$rolledBackVersions): void {
                $rolledBackVersions[] = $version;
            },
        );

        if ($count === 0) {
            $io->success('Nothing to rollback.');
            return Command::SUCCESS;
        }

        $io->info('Rolling back migrations...');
        $io->newLine();
        foreach ($rolledBackVersions as $version) {
            $io->writeln(\sprintf('    <fg=green>[OK]</> %s', $version));
        }
        $io->newLine();
        $io->success(\sprintf(
            'Rolled back %d %s.',
            $count,
            $count === 1 ? 'migration' : 'migrations',
        ));

        return Command::SUCCESS;
    }
}
