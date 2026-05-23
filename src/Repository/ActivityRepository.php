<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Athlete;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activity>
 */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    public function save(Activity $activity, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($activity);
        if ($flush) {
            $em->flush();
        }
    }

    public function findLatestStartDate(Athlete $athlete): ?\DateTimeImmutable
    {
        /** @var \DateTimeImmutable|null $result */
        $result = $this->createQueryBuilder('a')
            ->select('MAX(a.startDate)')
            ->where('a.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->getQuery()
            ->getSingleScalarResult();

        if (null === $result) {
            return null;
        }

        return $result instanceof \DateTimeImmutable
            ? $result
            : new \DateTimeImmutable((string) $result);
    }

    public function countForAthlete(Athlete $athlete): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Apply optional filters to a query builder for activities of an athlete.
     *
     * @param array{
     *     year?: int|null,
     *     month?: int|null,
     *     sport_type?: string|null,
     *     from?: \DateTimeImmutable|null,
     *     to?: \DateTimeImmutable|null,
     * } $filters
     */
    public function applyFilters(\Doctrine\ORM\QueryBuilder $qb, array $filters): \Doctrine\ORM\QueryBuilder
    {
        if (!empty($filters['year'])) {
            $qb->andWhere('EXTRACT(YEAR FROM a.startDateLocal) = :year')
                ->setParameter('year', $filters['year']);
        }
        if (!empty($filters['month'])) {
            $qb->andWhere('EXTRACT(MONTH FROM a.startDateLocal) = :month')
                ->setParameter('month', $filters['month']);
        }
        if (!empty($filters['sport_type'])) {
            $qb->andWhere('a.sportType = :sport')
                ->setParameter('sport', $filters['sport_type']);
        }
        if (!empty($filters['from'])) {
            $qb->andWhere('a.startDateLocal >= :from')
                ->setParameter('from', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $qb->andWhere('a.startDateLocal <= :to')
                ->setParameter('to', $filters['to']);
        }

        return $qb;
    }

    /**
     * Aggregate totals (distance, duration, elevation, count) for the given filters.
     *
     * @param array<string, mixed> $filters
     *
     * @return array{count: int, distance_m: float, moving_time_s: int, elevation_m: float}
     */
    public function totals(Athlete $athlete, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id) AS count, COALESCE(SUM(a.distance), 0) AS distance, COALESCE(SUM(a.movingTime), 0) AS moving, COALESCE(SUM(a.totalElevationGain), 0) AS elevation')
            ->where('a.athlete = :athlete')
            ->setParameter('athlete', $athlete);
        $this->applyFilters($qb, $filters);

        /** @var array<string, mixed> $row */
        $row = $qb->getQuery()->getSingleResult();

        return [
            'count' => (int) $row['count'],
            'distance_m' => (float) $row['distance'],
            'moving_time_s' => (int) $row['moving'],
            'elevation_m' => (float) $row['elevation'],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array{year: int, count: int, distance_m: float, moving_time_s: int, elevation_m: float}>
     */
    public function totalsByYear(Athlete $athlete, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'EXTRACT(YEAR FROM a.startDateLocal) AS year,
                 COUNT(a.id) AS count,
                 COALESCE(SUM(a.distance), 0) AS distance,
                 COALESCE(SUM(a.movingTime), 0) AS moving,
                 COALESCE(SUM(a.totalElevationGain), 0) AS elevation'
            )
            ->where('a.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->groupBy('year')
            ->orderBy('year', 'DESC');
        $this->applyFilters($qb, $filters);

        $out = [];
        /** @var array<string, mixed> $row */
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $out[] = [
                'year' => (int) $row['year'],
                'count' => (int) $row['count'],
                'distance_m' => (float) $row['distance'],
                'moving_time_s' => (int) $row['moving'],
                'elevation_m' => (float) $row['elevation'],
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array{sport_type: string, count: int, distance_m: float, moving_time_s: int}>
     */
    public function totalsBySportType(Athlete $athlete, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'a.sportType AS sport_type,
                 COUNT(a.id) AS count,
                 COALESCE(SUM(a.distance), 0) AS distance,
                 COALESCE(SUM(a.movingTime), 0) AS moving'
            )
            ->where('a.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->groupBy('a.sportType')
            ->orderBy('distance', 'DESC');
        $this->applyFilters($qb, $filters);

        $out = [];
        /** @var array<string, mixed> $row */
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $out[] = [
                'sport_type' => (string) $row['sport_type'],
                'count' => (int) $row['count'],
                'distance_m' => (float) $row['distance'],
                'moving_time_s' => (int) $row['moving'],
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array{year: int, month: int, count: int, distance_m: float, moving_time_s: int}>
     */
    public function totalsByMonth(Athlete $athlete, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select(
                'EXTRACT(YEAR FROM a.startDateLocal) AS year,
                 EXTRACT(MONTH FROM a.startDateLocal) AS month,
                 COUNT(a.id) AS count,
                 COALESCE(SUM(a.distance), 0) AS distance,
                 COALESCE(SUM(a.movingTime), 0) AS moving'
            )
            ->where('a.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->groupBy('year, month')
            ->orderBy('year', 'DESC')
            ->addOrderBy('month', 'DESC');
        $this->applyFilters($qb, $filters);

        $out = [];
        /** @var array<string, mixed> $row */
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $out[] = [
                'year' => (int) $row['year'],
                'month' => (int) $row['month'],
                'count' => (int) $row['count'],
                'distance_m' => (float) $row['distance'],
                'moving_time_s' => (int) $row['moving'],
            ];
        }

        return $out;
    }

    /**
     * @return list<int>
     */
    public function listYears(Athlete $athlete): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('DISTINCT EXTRACT(YEAR FROM a.startDateLocal) AS year')
            ->where('a.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->orderBy('year', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(static fn (array $r): int => (int) $r['year'], $rows));
    }

    /**
     * @return list<string>
     */
    public function listSportTypes(Athlete $athlete): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('DISTINCT a.sportType AS sport_type')
            ->where('a.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->orderBy('sport_type', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(static fn (array $r): string => (string) $r['sport_type'], $rows));
    }

    /**
     * Find activities whose start point falls within a given radius (in meters)
     * of a center, with optional date / sport filters. Used by the map renderer.
     *
     * @param array<string, mixed> $filters
     *
     * @return list<Activity>
     */
    public function findInRadius(
        Athlete $athlete,
        float $centerLat,
        float $centerLng,
        float $radiusMeters,
        array $filters = [],
    ): array {
        $sql = <<<'SQL'
                SELECT a.*
                FROM activity a
                WHERE a.athlete_id = :athlete_id
                  AND a.start_latlng IS NOT NULL
                  AND a.summary_polyline IS NOT NULL
                  AND a.summary_polyline <> ''
                  AND ST_DWithin(a.start_latlng, ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)::geography, :radius)
            SQL;

        $params = [
            'athlete_id' => $athlete->getId(),
            'lat' => $centerLat,
            'lng' => $centerLng,
            'radius' => $radiusMeters,
        ];

        if (!empty($filters['from'])) {
            $sql .= ' AND a.start_date_local >= :from';
            $params['from'] = $filters['from'] instanceof \DateTimeInterface
                ? $filters['from']->format('Y-m-d H:i:s')
                : $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND a.start_date_local <= :to';
            $params['to'] = $filters['to'] instanceof \DateTimeInterface
                ? $filters['to']->format('Y-m-d H:i:s')
                : $filters['to'];
        }
        if (!empty($filters['sport_types']) && is_array($filters['sport_types'])) {
            $sql .= ' AND a.sport_type = ANY(:sport_types)';
            $params['sport_types'] = $filters['sport_types'];
        }

        $em = $this->getEntityManager();
        $rsm = new \Doctrine\ORM\Query\ResultSetMappingBuilder($em);
        $rsm->addRootEntityFromClassMetadata(Activity::class, 'a');

        $query = $em->createNativeQuery($sql, $rsm);
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                $query->setParameter($k, $v, \Doctrine\DBAL\ArrayParameterType::STRING);
            } else {
                $query->setParameter($k, $v);
            }
        }

        /** @var list<Activity> $r */
        $r = $query->getResult();

        return $r;
    }
}
