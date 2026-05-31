<?php

declare(strict_types=1);

namespace App\Service\Strava;

use App\Entity\Activity;
use App\Entity\Athlete;
use App\Repository\ActivityRepository;
use App\Repository\AthleteRepository;
use App\Service\Roads\Matcher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates pulling activities from Strava into the local database.
 */
class Synchronizer
{
    /** Page size used against /athlete/activities (Strava max is 200). */
    private const PAGE_SIZE = 200;

    public function __construct(
        private readonly Client $client,
        private readonly ActivityMapper $mapper,
        private readonly ActivityRepository $activityRepository,
        private readonly AthleteRepository $athleteRepository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly Matcher $matcher,
    ) {
    }

    /**
     * Synchronize activities for the configured athlete.
     *
     * @param int|null      $afterTimestamp Unix timestamp; only activities after this time are fetched. Null = full history.
     * @param callable|null $onActivity     optional callback called for every imported activity (id, name)
     *
     * @return int number of new or updated activities written
     */
    public function sync(Athlete $athlete, ?int $afterTimestamp = null, ?callable $onActivity = null): int
    {
        $page = 1;
        $written = 0;

        while (true) {
            $batch = $this->client->listActivities($afterTimestamp, $page, self::PAGE_SIZE);
            if ([] === $batch) {
                break;
            }

            $touched = [];
            foreach ($batch as $raw) {
                if (!isset($raw['id'])) {
                    continue;
                }
                $id = (string) $raw['id'];
                $activity = $this->activityRepository->find($id) ?? new Activity($id, $athlete);
                $this->mapper->hydrate($activity, $raw);
                $this->activityRepository->save($activity, false);
                $touched[] = $activity;
                ++$written;

                if (null !== $onActivity) {
                    $onActivity($id, $activity->getName());
                }
            }

            $this->em->flush();
            // Match newly-imported activities against the imported road network
            // before clearing — activities are still managed entities here.
            foreach ($touched as $a) {
                try {
                    $this->matcher->matchActivity($a);
                } catch (\Throwable $e) {
                    $this->logger->warning('Road matching failed', ['activity' => $a->getId(), 'error' => $e->getMessage()]);
                }
            }
            $this->em->clear();
            $this->logger->info('Strava sync page processed', ['page' => $page, 'count' => count($batch)]);

            if (count($batch) < self::PAGE_SIZE) {
                break;
            }
            $athlete = $this->athleteRepository->find($athlete->getId()) ?? $athlete;
            ++$page;
        }

        $athlete = $this->athleteRepository->find($athlete->getId()) ?? $athlete;
        $athlete->setLastSyncedAt(new \DateTimeImmutable());
        $this->athleteRepository->save($athlete, true);

        return $written;
    }

    public function syncIncremental(Athlete $athlete, ?callable $onActivity = null): int
    {
        $latest = $this->activityRepository->findLatestStartDate($athlete);
        $after = null !== $latest ? $latest->getTimestamp() - 1 : null;

        return $this->sync($athlete, $after, $onActivity);
    }
}
