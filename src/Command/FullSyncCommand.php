<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Strava\AthleteProvider;
use App\Service\Strava\Synchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:strava:full-sync', description: 'Pull the entire Strava activity history.')]
final class FullSyncCommand extends Command
{
    public function __construct(
        private readonly AthleteProvider $athleteProvider,
        private readonly Synchronizer $synchronizer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $athlete = $this->athleteProvider->getOrBootstrap();

        $io->title(sprintf('Full sync for %s %s', $athlete->getFirstName(), $athlete->getLastName()));
        $count = $this->synchronizer->sync(
            $athlete,
            null,
            static fn (string $id, string $name) => $io->writeln(sprintf('  + %s — %s', $id, $name), OutputInterface::VERBOSITY_VERBOSE),
        );
        $io->success(sprintf('%d activity(ies) imported.', $count));

        return Command::SUCCESS;
    }
}
