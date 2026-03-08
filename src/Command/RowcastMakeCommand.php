<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastBundle\Command;

use AsceticSoft\RowcastSchema\Migration\MigrationGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'rowcast:make', description: 'Generate an empty RowcastSchema migration file')]
final class RowcastMakeCommand extends Command
{
    public function __construct(
        private readonly MigrationGenerator $generator,
        private readonly string $migrationsPath,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Rowcast -- make');

        $file = $this->generator->generate([], $this->migrationsPath);
        $io->success(\sprintf('Empty migration generated: %s', pathinfo($file, \PATHINFO_FILENAME)));
        $io->writeln('    ' . $file);

        return Command::SUCCESS;
    }
}
