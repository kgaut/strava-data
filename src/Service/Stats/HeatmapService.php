<?php

declare(strict_types=1);

namespace App\Service\Stats;

use App\Entity\Athlete;
use App\Repository\ActivityRepository;

/**
 * Daily activity volume + streak computations for the calendar heatmap.
 */
class HeatmapService
{
    public function __construct(private readonly ActivityRepository $activityRepository)
    {
    }

    /**
     * Sum of distance / moving time per local day, grouped by start_date_local.
     * Keys are 'Y-m-d' strings.
     *
     * @return array<string, array{count: int, distance_m: float, moving_time_s: int}>
     */
    public function dailyVolume(Athlete $athlete, Filters $filters): array
    {
        $qb = $this->activityRepository->createQueryBuilder('a')
            ->select(
                'DATE(a.startDateLocal) AS day,
                 COUNT(a.id) AS count,
                 COALESCE(SUM(a.distance), 0) AS distance,
                 COALESCE(SUM(a.movingTime), 0) AS moving'
            )
            ->where('a.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->groupBy('day');
        $this->activityRepository->applyFilters($qb, $filters->toRepositoryArray());

        $out = [];
        /** @var array<string, mixed> $row */
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $day = $row['day'];
            $key = $day instanceof \DateTimeInterface ? $day->format('Y-m-d') : (string) $day;
            // Some Doctrine setups return 'YYYY-MM-DD 00:00:00' — normalize.
            $key = substr($key, 0, 10);
            $out[$key] = [
                'count' => (int) $row['count'],
                'distance_m' => (float) $row['distance'],
                'moving_time_s' => (int) $row['moving'],
            ];
        }

        return $out;
    }

    /**
     * Compute streak statistics from a set of active dates.
     *
     * @param iterable<string> $activeDates 'Y-m-d' strings, any order, duplicates allowed
     *
     * @return array{longest: int, current: int, total_active: int}
     */
    public function streaks(iterable $activeDates, ?\DateTimeImmutable $referenceToday = null): array
    {
        $dates = [];
        foreach ($activeDates as $d) {
            $dates[$d] = true;
        }
        $sorted = array_keys($dates);
        sort($sorted);

        $longest = 0;
        $run = 0;
        $prev = null;
        foreach ($sorted as $day) {
            if (null === $prev) {
                $run = 1;
            } else {
                $diff = (new \DateTimeImmutable($prev))->diff(new \DateTimeImmutable($day))->days;
                $run = 1 === $diff ? $run + 1 : 1;
            }
            $longest = max($longest, $run);
            $prev = $day;
        }

        $today = $referenceToday ?? new \DateTimeImmutable('today');
        $current = 0;
        $cursor = $today;
        while (isset($dates[$cursor->format('Y-m-d')])) {
            ++$current;
            $cursor = $cursor->modify('-1 day');
        }
        // Allow a single skipped today: if today has no activity but yesterday does, treat yesterday as the head.
        if (0 === $current) {
            $yesterday = $today->modify('-1 day');
            $cursor = $yesterday;
            while (isset($dates[$cursor->format('Y-m-d')])) {
                ++$current;
                $cursor = $cursor->modify('-1 day');
            }
        }

        return [
            'longest' => $longest,
            'current' => $current,
            'total_active' => count($dates),
        ];
    }
}
