<?php

declare(strict_types=1);

namespace App\Service\Roads;

use App\Entity\AdminArea;
use App\Service\Overpass\Client as OverpassClient;
use App\Service\Overpass\OverpassException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Imports a department + all its communes into the admin_area table.
 *
 * Strategy: Overpass returns the relation list (lightweight, just tags +
 * ids), then for each relation we fetch the polygon from Nominatim
 * (it does the ring assembly server-side, so we don't need geoPHP).
 */
class AdminAreaImporter
{
    public function __construct(
        private readonly OverpassClient $overpass,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param callable|null $progress Optional (label, current, total) progress callback.
     */
    public function importDepartmentAndCommunes(int $departmentRelationId, ?callable $progress = null): int
    {
        $imported = 0;

        // 1. The department itself.
        $deptGeom = $this->overpass->nominatimGeometry('relation', $departmentRelationId);
        if (null === $deptGeom) {
            throw new OverpassException("Nominatim returned no geometry for relation $departmentRelationId");
        }
        $deptId = (string) $departmentRelationId;
        $this->upsert(
            $deptId,
            AdminArea::LEVEL_DEPARTMENT,
            $this->fetchRelationName($departmentRelationId) ?? "Department $departmentRelationId",
            $this->geoJsonToMultiPolygonWkt($deptGeom),
            null,
        );
        ++$imported;
        if (null !== $progress) {
            $progress('department', 1, 1);
        }

        // 2. List child communes via Overpass area magic
        //    Overpass area id for a relation = 3_600_000_000 + relation_id.
        $areaId = 3_600_000_000 + $departmentRelationId;
        $ql = <<<QL
            [out:json][timeout:120];
            relation["boundary"="administrative"]["admin_level"="8"](area:$areaId);
            out tags;
        QL;
        $elements = $this->overpass->query($ql);
        $this->logger->info('Discovered communes', ['count' => count($elements)]);

        $total = count($elements);
        $i = 0;
        foreach ($elements as $element) {
            ++$i;
            $relationId = isset($element['id']) ? (int) $element['id'] : 0;
            if (0 === $relationId) {
                continue;
            }
            $name = isset($element['tags']['name']) ? (string) $element['tags']['name'] : "Relation $relationId";
            $insee = isset($element['tags']['ref:INSEE']) ? (string) $element['tags']['ref:INSEE'] : null;

            $geom = $this->overpass->nominatimGeometry('relation', $relationId);
            if (null === $geom) {
                $this->logger->warning('No geometry for commune', ['id' => $relationId, 'name' => $name]);
                continue;
            }
            $this->upsert(
                (string) $relationId,
                AdminArea::LEVEL_COMMUNE,
                $name,
                $this->geoJsonToMultiPolygonWkt($geom),
                $insee,
            );
            ++$imported;

            // Flush every 50 to keep memory bounded.
            if (0 === $imported % 50) {
                $this->em->flush();
                $this->em->clear();
            }
            if (null !== $progress) {
                $progress($name, $i, $total);
            }
        }

        $this->em->flush();
        $this->em->clear();

        return $imported;
    }

    /**
     * Upsert by id. Uses native SQL with ST_GeomFromText because Doctrine
     * ORM upserts on geometry columns are awkward.
     */
    private function upsert(string $id, int $level, string $name, string $multiPolygonWkt, ?string $code): void
    {
        $sql = <<<'SQL'
            INSERT INTO admin_area (id, admin_level, name, code, geometry, imported_at)
            VALUES (:id, :level, :name, :code, ST_GeomFromText(:wkt, 4326)::geography, NOW())
            ON CONFLICT (id) DO UPDATE SET
                name = EXCLUDED.name,
                code = EXCLUDED.code,
                geometry = EXCLUDED.geometry,
                imported_at = NOW()
        SQL;

        $this->em->getConnection()->executeStatement($sql, [
            'id' => $id,
            'level' => $level,
            'name' => $name,
            'code' => $code,
            'wkt' => $multiPolygonWkt,
        ]);
    }

    /**
     * Convert a GeoJSON Polygon or MultiPolygon to MULTIPOLYGON WKT
     * suitable for ST_GeomFromText. PostGIS will normalize it.
     *
     * @param array<string, mixed> $geom
     */
    private function geoJsonToMultiPolygonWkt(array $geom): string
    {
        $type = (string) ($geom['type'] ?? '');
        $coords = $geom['coordinates'] ?? [];
        if (!is_array($coords)) {
            throw new OverpassException('Invalid GeoJSON: missing coordinates');
        }

        if ('Polygon' === $type) {
            return 'MULTIPOLYGON('.$this->ringsToWkt($coords).')';
        }
        if ('MultiPolygon' === $type) {
            $polys = [];
            foreach ($coords as $rings) {
                if (!is_array($rings)) {
                    continue;
                }
                $polys[] = $this->ringsToWkt($rings);
            }

            return 'MULTIPOLYGON('.implode(',', $polys).')';
        }
        throw new OverpassException("Unsupported GeoJSON geometry type: $type");
    }

    /**
     * @param array<int, mixed> $rings
     */
    private function ringsToWkt(array $rings): string
    {
        $out = [];
        foreach ($rings as $ring) {
            if (!is_array($ring)) {
                continue;
            }
            $pts = [];
            foreach ($ring as $pt) {
                if (is_array($pt) && isset($pt[0], $pt[1])) {
                    $pts[] = sprintf('%F %F', (float) $pt[0], (float) $pt[1]);
                }
            }
            $out[] = '('.implode(',', $pts).')';
        }

        return '('.implode(',', $out).')';
    }

    private function fetchRelationName(int $relationId): ?string
    {
        $ql = "[out:json][timeout:30]; relation($relationId); out tags;";
        try {
            $elements = $this->overpass->query($ql, 60);
        } catch (OverpassException) {
            return null;
        }
        foreach ($elements as $el) {
            if (isset($el['tags']['name'])) {
                return (string) $el['tags']['name'];
            }
        }

        return null;
    }
}
