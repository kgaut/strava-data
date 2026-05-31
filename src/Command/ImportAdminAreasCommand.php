<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Roads\AdminAreaImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:roads:import-admin',
    description: 'Import a department and all its communes from OSM (admin_area table).',
)]
final class ImportAdminAreasCommand extends Command
{
    public function __construct(private readonly AdminAreaImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'osm-relation',
            null,
            InputOption::VALUE_REQUIRED,
            'OSM relation id of the department (find it on openstreetmap.org → search → relation row)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rel = (int) ($input->getOption('osm-relation') ?? 0);
        if ($rel <= 0) {
            $io->error('--osm-relation is required (e.g. --osm-relation=7406 for Gironde).');

            return Command::FAILURE;
        }

        $io->title("Importing admin areas for OSM relation $rel");
        $progressBar = $io->createProgressBar();
        $progressBar->setFormat(' %current%/%max% [%bar%] %message%');
        $progressBar->setMessage('starting');

        $count = $this->importer->importDepartmentAndCommunes(
            $rel,
            function (string $label, int $i, int $total) use ($progressBar) {
                $progressBar->setMaxSteps($total);
                $progressBar->setProgress($i);
                $progressBar->setMessage($label);
            },
        );
        $progressBar->finish();
        $output->writeln('');

        $io->success("Imported / updated $count admin areas.");

        return Command::SUCCESS;
    }
}
