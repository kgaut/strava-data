<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ActivityRepository;
use App\Service\Roads\Matcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:roads:match',
    description: 'Match activities against imported road segments (populate road_visit).',
)]
final class MatchRoadsCommand extends Command
{
    public function __construct(
        private readonly Matcher $matcher,
        private readonly ActivityRepository $activityRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('all', null, InputOption::VALUE_NONE, 'Match every activity in the database.')
            ->addOption('activity', null, InputOption::VALUE_REQUIRED, 'Match a single activity by id.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $all = (bool) $input->getOption('all');
        $activityId = $input->getOption('activity');

        if (!$all && null === $activityId) {
            $io->error('Pass either --all or --activity=<id>.');

            return Command::FAILURE;
        }

        if (null !== $activityId) {
            $activity = $this->activityRepository->find((string) $activityId);
            if (null === $activity) {
                $io->error("Activity $activityId not found.");

                return Command::FAILURE;
            }
            $count = $this->matcher->matchActivity($activity);
            $io->success("Marked $count segments for activity $activityId.");

            return Command::SUCCESS;
        }

        $io->title('Matching every activity against road segments');
        $progressBar = $io->createProgressBar();
        $count = $this->matcher->matchAll(
            function (int $i, int $total) use ($progressBar) {
                $progressBar->setMaxSteps($total);
                $progressBar->setProgress($i);
            },
        );
        $progressBar->finish();
        $output->writeln('');
        $io->success("Marked $count segment-visits across all activities.");

        return Command::SUCCESS;
    }
}
