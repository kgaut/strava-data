<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\AthleteRepository;
use App\Service\Strava\Synchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:strava:sync', description: 'Incrementally sync new Strava activities since the last imported one.')]
final class SyncCommand extends Command
{
    public function __construct(
        private readonly AthleteRepository $athleteRepository,
        private readonly Synchronizer $synchronizer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $athlete = $this->athleteRepository->findFirst();
        if (null === $athlete) {
            $io->error('No athlete connected. Log in via the web UI first.');

            return Command::FAILURE;
        }

        $io->title(sprintf('Incremental sync for %s %s', $athlete->getFirstName(), $athlete->getLastName()));
        $count = $this->synchronizer->syncIncremental(
            $athlete,
            static fn (string $id, string $name) => $io->writeln(sprintf('  + %s — %s', $id, $name), OutputInterface::VERBOSITY_VERBOSE),
        );
        $io->success(sprintf('%d activity(ies) imported or updated.', $count));

        return Command::SUCCESS;
    }
}
