<?php

declare(strict_types=1);

namespace App\Service\Roads;

use App\Entity\Activity;
use App\Service\Map\PolylineDecoder;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * For each activity polyline, find road segments within a buffer distance and
 * record the first visit in road_visit. Uses PostGIS ST_DWithin on the
 * geography column — accurate in meters.
 */
class Matcher
{
    public const BUFFER_METERS = 15;

    public function __construct(
        private readonly PolylineDecoder $polylineDecoder,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Match a single activity against the road network. Returns the number of
     * newly-marked (or earlier-marked) segments.
     */
    public function matchActivity(Activity $activity): int
    {
        $polyline = $activity->getSummaryPolyline();
        if (null === $polyline || '' === $polyline) {
            return 0;
        }
        $points = $this->polylineDecoder->decode($polyline);
        if (count($points) < 2) {
            return 0;
        }

        $pieces = [];
        foreach ($points as [$lat, $lng]) {
            $pieces[] = sprintf('%F %F', $lng, $lat);
        }
        $wkt = 'LINESTRING('.implode(',', $pieces).')';

        return $this->upsertVisitsByLine(
            $activity->getId(),
            $activity->getStartDate(),
            $wkt,
        );
    }

    /**
     * Match every activity that has a polyline. Heavy — run once on import.
     */
    public function matchAll(?callable $progress = null): int
    {
        /** @var Connection $conn */
        $conn = $this->em->getConnection();
        $activities = $conn->fetchAllAssociative(<<<'SQL'
            SELECT id, summary_polyline, start_date
            FROM activity
            WHERE summary_polyline IS NOT NULL AND summary_polyline <> ''
            ORDER BY start_date ASC
        SQL);

        $total = count($activities);
        $i = 0;
        $marked = 0;
        foreach ($activities as $row) {
            ++$i;
            $points = $this->polylineDecoder->decode((string) $row['summary_polyline']);
            if (count($points) < 2) {
                continue;
            }
            $pieces = [];
            foreach ($points as [$lat, $lng]) {
                $pieces[] = sprintf('%F %F', $lng, $lat);
            }
            $wkt = 'LINESTRING('.implode(',', $pieces).')';
            $started = $row['start_date'] instanceof \DateTimeInterface
                ? \DateTimeImmutable::createFromInterface($row['start_date'])
                : new \DateTimeImmutable((string) $row['start_date']);
            $marked += $this->upsertVisitsByLine((string) $row['id'], $started, $wkt);
            if (null !== $progress && 0 === $i % 25) {
                $progress($i, $total);
            }
        }

        return $marked;
    }

    /**
     * The actual upsert: find all road_segment within BUFFER_METERS and
     * INSERT them into road_visit; if a row already exists for the segment
     * but with a later first_visited_at, replace it so we always keep the
     * earliest activity that touched the segment.
     */
    private function upsertVisitsByLine(string $activityId, \DateTimeImmutable $startedAt, string $lineWkt): int
    {
        $sql = <<<SQL
            WITH candidates AS (
                SELECT r.id AS road_segment_id
                FROM road_segment r
                WHERE ST_DWithin(r.geometry, ST_GeomFromText(:wkt, 4326)::geography, :buffer)
            )
            INSERT INTO road_visit (road_segment_id, first_activity_id, first_visited_at)
            SELECT c.road_segment_id, :activity_id, :started_at
            FROM candidates c
            ON CONFLICT (road_segment_id) DO UPDATE SET
                first_activity_id = EXCLUDED.first_activity_id,
                first_visited_at = EXCLUDED.first_visited_at
            WHERE EXCLUDED.first_visited_at < road_visit.first_visited_at
        SQL;

        $affected = (int) $this->em->getConnection()->executeStatement($sql, [
            'wkt' => $lineWkt,
            'buffer' => self::BUFFER_METERS,
            'activity_id' => $activityId,
            'started_at' => $startedAt->format('Y-m-d H:i:s'),
        ]);

        $this->logger->debug('Activity matched', ['activity' => $activityId, 'segments' => $affected]);

        return $affected;
    }
}
