<?php

declare(strict_types=1);

namespace App\Service\Strava;

use App\Entity\Activity;
use App\Entity\Athlete;
use App\Repository\ActivityRepository;
use App\Repository\AthleteRepository;
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
    ) {
    }

    /**
     * Synchronize activities for an athlete.
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
            $batch = $this->client->listActivities($athlete, $afterTimestamp, $page, self::PAGE_SIZE);
            if ([] === $batch) {
                break;
            }

            foreach ($batch as $raw) {
                if (!isset($raw['id'])) {
                    continue;
                }
                $id = (string) $raw['id'];
                $activity = $this->activityRepository->find($id) ?? new Activity($id, $athlete);
                $this->mapper->hydrate($activity, $raw);
                $this->activityRepository->save($activity, false);
                ++$written;

                if (null !== $onActivity) {
                    $onActivity($id, $activity->getName());
                }
            }

            $this->em->flush();
            $this->em->clear();
            $this->logger->info('Strava sync page processed', ['page' => $page, 'count' => count($batch)]);

            // Strava returns up to PAGE_SIZE; fewer means the last page.
            if (count($batch) < self::PAGE_SIZE) {
                break;
            }
            // Re-fetch the athlete after clear so the next iteration uses a managed reference.
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
        // Strava's `after` param is exclusive — subtract 1s to avoid missing the latest known activity.
        $after = null !== $latest ? $latest->getTimestamp() - 1 : null;

        return $this->sync($athlete, $after, $onActivity);
    }
}
