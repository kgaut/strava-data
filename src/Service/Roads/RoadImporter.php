<?php

declare(strict_types=1);

namespace App\Service\Roads;

use App\Service\Overpass\Client as OverpassClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Imports OSM highway ways within an already-imported department area.
 * Each way becomes a row in road_segment with length_m precomputed and
 * commune_id / department_id assigned via ST_Intersects against admin_area.
 */
class RoadImporter
{
    /** Highway tag values worth importing (drops construction, proposed, etc.). */
    private const ROAD_HIGHWAY_VALUES = [
        'motorway', 'trunk', 'primary', 'secondary', 'tertiary', 'unclassified',
        'residential', 'living_street', 'service', 'pedestrian', 'track',
        'cycleway', 'footway', 'path', 'bridleway', 'steps',
        'motorway_link', 'trunk_link', 'primary_link', 'secondary_link', 'tertiary_link',
    ];

    public function __construct(
        private readonly OverpassClient $overpass,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param callable|null $progress (label, current, total)
     */
    public function importByDepartment(int $departmentRelationId, ?callable $progress = null): int
    {
        $areaId = 3_600_000_000 + $departmentRelationId;
        $highways = implode('|', self::ROAD_HIGHWAY_VALUES);
        $ql = <<<QL
            [out:json][timeout:300];
            way["highway"~"^($highways)$"](area:$areaId);
            out tags geom;
        QL;

        $this->logger->info('Fetching road ways from Overpass', ['area' => $areaId]);
        $elements = $this->overpass->query($ql, 360);
        $this->logger->info('Got road ways', ['count' => count($elements)]);

        $batch = [];
        $written = 0;
        $i = 0;
        $total = count($elements);
        foreach ($elements as $element) {
            ++$i;
            $id = isset($element['id']) ? (int) $element['id'] : 0;
            $highway = isset($element['tags']['highway']) ? (string) $element['tags']['highway'] : null;
            $geometry = $element['geometry'] ?? null;
            if (0 === $id || null === $highway || !is_array($geometry) || count($geometry) < 2) {
                continue;
            }

            $points = [];
            foreach ($geometry as $pt) {
                if (is_array($pt) && isset($pt['lat'], $pt['lon'])) {
                    $points[] = sprintf('%F %F', (float) $pt['lon'], (float) $pt['lat']);
                }
            }
            if (count($points) < 2) {
                continue;
            }

            $batch[] = [
                'id' => (string) $id,
                'highway' => $highway,
                'name' => isset($element['tags']['name']) ? (string) $element['tags']['name'] : null,
                'surface' => isset($element['tags']['surface']) ? (string) $element['tags']['surface'] : null,
                'wkt' => 'LINESTRING('.implode(',', $points).')',
            ];

            if (count($batch) >= 500) {
                $this->flushBatch($batch);
                $written += count($batch);
                $batch = [];
            }
            if (null !== $progress && 0 === $i % 200) {
                $progress('importing', $i, $total);
            }
        }
        if ([] !== $batch) {
            $this->flushBatch($batch);
            $written += count($batch);
        }

        $this->logger->info('Assigning admin areas via ST_Intersects');
        $this->assignAdminAreas();

        return $written;
    }

    /**
     * @param list<array{id: string, highway: string, name: ?string, surface: ?string, wkt: string}> $batch
     */
    private function flushBatch(array $batch): void
    {
        $conn = $this->em->getConnection();
        $conn->beginTransaction();
        try {
            foreach ($batch as $row) {
                $conn->executeStatement(
                    <<<'SQL'
                        INSERT INTO road_segment (id, highway, name, surface, geometry, length_m, imported_at)
                        VALUES (
                            :id, :highway, :name, :surface,
                            ST_GeomFromText(:wkt, 4326)::geography,
                            ST_Length(ST_GeomFromText(:wkt, 4326)::geography),
                            NOW()
                        )
                        ON CONFLICT (id) DO UPDATE SET
                            highway = EXCLUDED.highway,
                            name = EXCLUDED.name,
                            surface = EXCLUDED.surface,
                            geometry = EXCLUDED.geometry,
                            length_m = EXCLUDED.length_m,
                            imported_at = NOW()
                    SQL,
                    $row,
                );
            }
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * Backfill commune_id and department_id for any segments where they are NULL
     * (or were just imported). Single pass — cheaper than per-segment matching.
     */
    private function assignAdminAreas(): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement(
            <<<SQL
                UPDATE road_segment r
                SET commune_id = sub.area_id
                FROM (
                    SELECT r2.id AS seg_id, a.id AS area_id
                    FROM road_segment r2
                    JOIN admin_area a ON a.admin_level = :commune AND ST_Intersects(a.geometry, r2.geometry)
                ) sub
                WHERE r.id = sub.seg_id
            SQL,
            ['commune' => \App\Entity\AdminArea::LEVEL_COMMUNE],
        );

        $conn->executeStatement(
            <<<SQL
                UPDATE road_segment r
                SET department_id = sub.area_id
                FROM (
                    SELECT r2.id AS seg_id, a.id AS area_id
                    FROM road_segment r2
                    JOIN admin_area a ON a.admin_level = :department AND ST_Intersects(a.geometry, r2.geometry)
                ) sub
                WHERE r.id = sub.seg_id
            SQL,
            ['department' => \App\Entity\AdminArea::LEVEL_DEPARTMENT],
        );
    }
}
