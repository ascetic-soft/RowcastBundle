<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastBundle\Command;

use AsceticSoft\RowcastSchema\Migration\MigrationGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
        $file = $this->generator->generate([], $this->migrationsPath);
        $output->writeln(\sprintf('Empty migration generated: %s', $file));

        return Command::SUCCESS;
    }
}
