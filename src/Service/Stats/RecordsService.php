<?php

declare(strict_types=1);

namespace App\Service\Stats;

use App\Entity\Athlete;
use App\Repository\ActivityRepository;

/**
 * Personal-best summaries computed from per-activity totals only.
 *
 * No streams support yet, so we can't answer "fastest 5K embedded in any run".
 * We surface the top-N activities by various single-activity metrics instead.
 */
class RecordsService
{
    public const DEFAULT_LIMIT = 5;

    public function __construct(private readonly ActivityRepository $activityRepository)
    {
    }

    /**
     * @return list<array{id: string, name: string, sport_type: string, start_date: \DateTimeImmutable, value: float}>
     */
    public function longestByDistance(Athlete $athlete, Filters $filters, int $limit = self::DEFAULT_LIMIT): array
    {
        return $this->topN($athlete, $filters, 'a.distance', 'distance', $limit);
    }

    /**
     * @return list<array{id: string, name: string, sport_type: string, start_date: \DateTimeImmutable, value: float}>
     */
    public function longestByTime(Athlete $athlete, Filters $filters, int $limit = self::DEFAULT_LIMIT): array
    {
        return $this->topN($athlete, $filters, 'a.movingTime', 'moving_time', $limit);
    }

    /**
     * @return list<array{id: string, name: string, sport_type: string, start_date: \DateTimeImmutable, value: float}>
     */
    public function highestElevation(Athlete $athlete, Filters $filters, int $limit = self::DEFAULT_LIMIT): array
    {
        return $this->topN($athlete, $filters, 'a.totalElevationGain', 'elevation', $limit);
    }

    /**
     * Top activities by average speed, restricted to those covering at least
     * $minDistanceMeters — single-km sprints would otherwise dominate.
     *
     * @return list<array{id: string, name: string, sport_type: string, start_date: \DateTimeImmutable, value: float}>
     */
    public function fastestAvgSpeed(Athlete $athlete, Filters $filters, float $minDistanceMeters = 5000.0, int $limit = self::DEFAULT_LIMIT): array
    {
        $qb = $this->activityRepository->createQueryBuilder('a')
            ->select('a.id, a.name, a.sportType, a.startDateLocal, a.averageSpeed AS value')
            ->where('a.athlete = :athlete')
            ->andWhere('a.averageSpeed IS NOT NULL')
            ->andWhere('a.distance >= :minDistance')
            ->setParameter('athlete', $athlete)
            ->setParameter('minDistance', $minDistanceMeters)
            ->orderBy('a.averageSpeed', 'DESC')
            ->setMaxResults($limit);
        $this->activityRepository->applyFilters($qb, $filters->toRepositoryArray());

        return $this->hydrate($qb->getQuery()->getArrayResult());
    }

    /**
     * @return list<array{id: string, name: string, sport_type: string, start_date: \DateTimeImmutable, value: float}>
     */
    public function highestAvgHeartrate(Athlete $athlete, Filters $filters, int $limit = self::DEFAULT_LIMIT): array
    {
        $qb = $this->activityRepository->createQueryBuilder('a')
            ->select('a.id, a.name, a.sportType, a.startDateLocal, a.averageHeartrate AS value')
            ->where('a.athlete = :athlete')
            ->andWhere('a.hasHeartrate = TRUE')
            ->andWhere('a.averageHeartrate IS NOT NULL')
            ->setParameter('athlete', $athlete)
            ->orderBy('a.averageHeartrate', 'DESC')
            ->setMaxResults($limit);
        $this->activityRepository->applyFilters($qb, $filters->toRepositoryArray());

        return $this->hydrate($qb->getQuery()->getArrayResult());
    }

    /**
     * @return list<array{id: string, name: string, sport_type: string, start_date: \DateTimeImmutable, value: float}>
     */
    private function topN(Athlete $athlete, Filters $filters, string $column, string $alias, int $limit): array
    {
        $qb = $this->activityRepository->createQueryBuilder('a')
            ->select('a.id, a.name, a.sportType, a.startDateLocal, '.$column.' AS value')
            ->where('a.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->orderBy($column, 'DESC')
            ->setMaxResults($limit);
        $this->activityRepository->applyFilters($qb, $filters->toRepositoryArray());

        return $this->hydrate($qb->getQuery()->getArrayResult());
    }

    /**
     * @param array<mixed> $rows
     *
     * @return list<array{id: string, name: string, sport_type: string, start_date: \DateTimeImmutable, value: float}>
     */
    private function hydrate(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $start = $row['startDateLocal'] ?? null;
            $out[] = [
                'id' => (string) ($row['id'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'sport_type' => (string) ($row['sportType'] ?? ''),
                'start_date' => $start instanceof \DateTimeImmutable
                    ? $start
                    : new \DateTimeImmutable(is_scalar($start) ? (string) $start : 'now'),
                'value' => (float) ($row['value'] ?? 0),
            ];
        }

        return $out;
    }
}
