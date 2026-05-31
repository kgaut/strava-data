<?php

declare(strict_types=1);

namespace App\Service\Stats;

use App\Entity\Athlete;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Histogram builders for activity distance, speed, and elevation.
 *
 * Buckets are computed in PHP after pulling the raw values: keeps the SQL
 * simple, avoids Postgres-specific WIDTH_BUCKET coupling in tests.
 */
class DistributionsService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return list<array{label: string, count: int}>
     */
    public function distanceKmHistogram(Athlete $athlete, Filters $filters, int $bucketKm = 5): array
    {
        $values = $this->pullValues($athlete, $filters, 'distance');
        $values = array_map(static fn (float $m): float => $m / 1000.0, $values);

        return $this->bucketize($values, $bucketKm, ' km');
    }

    /**
     * @return list<array{label: string, count: int}>
     */
    public function speedKmhHistogram(Athlete $athlete, Filters $filters, int $bucketKmh = 2): array
    {
        $values = $this->pullValues($athlete, $filters, 'average_speed');
        $values = array_map(static fn (float $ms): float => $ms * 3.6, $values);

        return $this->bucketize($values, $bucketKmh, ' km/h');
    }

    /**
     * @return list<array{label: string, count: int}>
     */
    public function elevationHistogram(Athlete $athlete, Filters $filters, int $bucketM = 100): array
    {
        $values = $this->pullValues($athlete, $filters, 'total_elevation_gain');

        return $this->bucketize($values, $bucketM, ' m');
    }

    /**
     * @return list<float>
     */
    private function pullValues(Athlete $athlete, Filters $filters, string $column): array
    {
        $sql = "SELECT a.$column AS v FROM activity a WHERE a.athlete_id = :athlete_id AND a.$column IS NOT NULL";
        $params = ['athlete_id' => $athlete->getId()];
        $types = [];

        $f = $filters->toRepositoryArray();
        if (!empty($f['year'])) {
            $sql .= ' AND EXTRACT(YEAR FROM a.start_date_local) = :year';
            $params['year'] = $f['year'];
        }
        if (!empty($f['month'])) {
            $sql .= ' AND EXTRACT(MONTH FROM a.start_date_local) = :month';
            $params['month'] = $f['month'];
        }
        if (!empty($f['sport_type'])) {
            $sql .= ' AND a.sport_type = :sport';
            $params['sport'] = $f['sport_type'];
        }
        if (!empty($f['from'])) {
            $sql .= ' AND a.start_date_local >= :from';
            $params['from'] = $f['from'] instanceof \DateTimeInterface ? $f['from']->format('Y-m-d H:i:s') : (string) $f['from'];
        }
        if (!empty($f['to'])) {
            $sql .= ' AND a.start_date_local <= :to';
            $params['to'] = $f['to'] instanceof \DateTimeInterface ? $f['to']->format('Y-m-d H:i:s') : (string) $f['to'];
        }
        if (!empty($f['days_of_week']) && is_array($f['days_of_week'])) {
            $sql .= ' AND EXTRACT(DOW FROM a.start_date_local) = ANY(:dow)';
            $params['dow'] = $f['days_of_week'];
            $types['dow'] = ArrayParameterType::INTEGER;
        }
        if (null !== ($f['hour_from'] ?? null) && null !== ($f['hour_to'] ?? null)) {
            if ($f['hour_from'] <= $f['hour_to']) {
                $sql .= ' AND EXTRACT(HOUR FROM a.start_date_local) BETWEEN :hf AND :ht';
            } else {
                $sql .= ' AND (EXTRACT(HOUR FROM a.start_date_local) >= :hf OR EXTRACT(HOUR FROM a.start_date_local) <= :ht)';
            }
            $params['hf'] = $f['hour_from'];
            $params['ht'] = $f['hour_to'];
        }

        $rows = $this->connection->fetchAllAssociative($sql, $params, $types);

        $values = [];
        foreach ($rows as $row) {
            $values[] = (float) $row['v'];
        }

        return $values;
    }

    /**
     * @param list<float> $values
     *
     * @return list<array{label: string, count: int}>
     */
    private function bucketize(array $values, int $bucket, string $unit): array
    {
        if ([] === $values || $bucket <= 0) {
            return [];
        }

        $max = (int) ceil(max($values) / $bucket) * $bucket;
        $counts = [];
        for ($lo = 0; $lo < $max; $lo += $bucket) {
            $counts[$lo] = 0;
        }
        foreach ($values as $v) {
            $idx = (int) (floor($v / $bucket) * $bucket);
            $idx = min($idx, $max - $bucket);
            $counts[$idx] = ($counts[$idx] ?? 0) + 1;
        }

        $out = [];
        foreach ($counts as $lo => $count) {
            $out[] = ['label' => sprintf('%d–%d%s', $lo, $lo + $bucket, $unit), 'count' => $count];
        }

        return $out;
    }
}
