<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Roads\RoadImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:roads:import-roads',
    description: 'Import every OSM highway way in the given department (road_segment table).',
)]
final class ImportRoadsCommand extends Command
{
    public function __construct(private readonly RoadImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'osm-relation',
            null,
            InputOption::VALUE_REQUIRED,
            'OSM relation id of the department to scan.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rel = (int) ($input->getOption('osm-relation') ?? 0);
        if ($rel <= 0) {
            $io->error('--osm-relation is required.');

            return Command::FAILURE;
        }

        $io->title("Importing road network for OSM relation $rel (this may take 1-5 minutes)");

        $progressBar = $io->createProgressBar();
        $count = $this->importer->importByDepartment(
            $rel,
            static function (string $label, int $i, int $total) use ($progressBar) {
                $progressBar->setMaxSteps($total);
                $progressBar->setProgress($i);
            },
        );
        $progressBar->finish();
        $output->writeln('');

        $io->success("Imported / updated $count road segments.");

        return Command::SUCCESS;
    }
}
