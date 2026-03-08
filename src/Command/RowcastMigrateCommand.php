<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastBundle\Command;

use AsceticSoft\RowcastSchema\Migration\MigrationRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
        $io = new SymfonyStyle($input, $output);
        $io->title('Rowcast -- migrate');

        $appliedVersions = [];
        $count = $this->runner->migrate(
            $this->migrationsPath,
            static function (string $version) use (&$appliedVersions): void {
                $appliedVersions[] = $version;
            },
        );

        if ($count === 0) {
            $io->success('Nothing to migrate.');
            return Command::SUCCESS;
        }

        $io->info('Applying migrations...');
        $io->newLine();
        foreach ($appliedVersions as $version) {
            $io->writeln(\sprintf('    <fg=green>[OK]</> %s', $version));
        }
        $io->newLine();
        $io->success(\sprintf(
            'Applied %d %s.',
            $count,
            $count === 1 ? 'migration' : 'migrations',
        ));

        return Command::SUCCESS;
    }
}
