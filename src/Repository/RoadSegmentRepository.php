<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AdminArea;
use App\Entity\RoadSegment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RoadSegment>
 */
class RoadSegmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoadSegment::class);
    }

    public function save(RoadSegment $segment, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($segment);
        if ($flush) {
            $em->flush();
        }
    }

    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Coverage stats per admin area at the requested level.
     *
     * @return list<array{area_id: string, area_name: string, code: ?string, total_m: float, visited_m: float, pct: float}>
     */
    public function coverageByAdminArea(int $adminLevel): array
    {
        $column = AdminArea::LEVEL_DEPARTMENT === $adminLevel ? 'department_id' : 'commune_id';

        $sql = <<<SQL
            SELECT a.id AS area_id,
                   a.name AS area_name,
                   a.code AS code,
                   COALESCE(SUM(r.length_m), 0) AS total_m,
                   COALESCE(SUM(CASE WHEN v.road_segment_id IS NOT NULL THEN r.length_m ELSE 0 END), 0) AS visited_m
            FROM admin_area a
            LEFT JOIN road_segment r ON r.$column = a.id
            LEFT JOIN road_visit v ON v.road_segment_id = r.id
            WHERE a.admin_level = :level
            GROUP BY a.id, a.name, a.code
            ORDER BY visited_m DESC, area_name ASC
        SQL;

        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, ['level' => $adminLevel]);

        $out = [];
        foreach ($rows as $row) {
            $total = (float) $row['total_m'];
            $visited = (float) $row['visited_m'];
            $out[] = [
                'area_id' => (string) $row['area_id'],
                'area_name' => (string) $row['area_name'],
                'code' => isset($row['code']) ? (string) $row['code'] : null,
                'total_m' => $total,
                'visited_m' => $visited,
                'pct' => $total > 0 ? 100.0 * $visited / $total : 0.0,
            ];
        }

        return $out;
    }

    /**
     * Pull simplified road geometries (as GeoJSON LineStrings) intersecting a bbox,
     * plus the visited flag. Used by the /coverage Leaflet endpoint.
     *
     * @return list<array{id: string, highway: string, name: ?string, visited: bool, geometry: string}>
     */
    public function findGeoJsonInBbox(float $minLat, float $minLng, float $maxLat, float $maxLng): array
    {
        $sql = <<<'SQL'
            SELECT r.id,
                   r.highway,
                   r.name,
                   v.road_segment_id IS NOT NULL AS visited,
                   ST_AsGeoJSON(ST_Simplify(r.geometry::geometry, 0.00005)) AS geometry
            FROM road_segment r
            LEFT JOIN road_visit v ON v.road_segment_id = r.id
            WHERE r.geometry && ST_MakeEnvelope(:min_lng, :min_lat, :max_lng, :max_lat, 4326)::geography
        SQL;

        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, [
            'min_lat' => $minLat,
            'min_lng' => $minLng,
            'max_lat' => $maxLat,
            'max_lng' => $maxLng,
        ]);

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (string) $row['id'],
                'highway' => (string) $row['highway'],
                'name' => isset($row['name']) ? (string) $row['name'] : null,
                'visited' => (bool) $row['visited'],
                'geometry' => (string) $row['geometry'],
            ];
        }

        return $out;
    }

    /**
     * Lifetime totals: how much road is in the imported region and how much has been visited.
     *
     * @return array{total_m: float, visited_m: float, pct: float}
     */
    public function globalCoverage(): array
    {
        $sql = <<<'SQL'
            SELECT COALESCE(SUM(r.length_m), 0) AS total_m,
                   COALESCE(SUM(CASE WHEN v.road_segment_id IS NOT NULL THEN r.length_m ELSE 0 END), 0) AS visited_m
            FROM road_segment r
            LEFT JOIN road_visit v ON v.road_segment_id = r.id
        SQL;

        $row = $this->getEntityManager()->getConnection()->fetchAssociative($sql);
        $total = (float) ($row['total_m'] ?? 0);
        $visited = (float) ($row['visited_m'] ?? 0);

        return [
            'total_m' => $total,
            'visited_m' => $visited,
            'pct' => $total > 0 ? 100.0 * $visited / $total : 0.0,
        ];
    }
}
