<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AdminArea;
use App\Repository\ActivityRepository;
use App\Repository\RoadSegmentRepository;
use App\Repository\RoadVisitRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CoverageController extends AbstractController
{
    public function __construct(
        private readonly RoadSegmentRepository $roadSegments,
        private readonly RoadVisitRepository $roadVisits,
        private readonly ActivityRepository $activityRepository,
        private readonly Connection $connection,
    ) {
    }

    #[Route('/coverage', name: 'app_coverage')]
    public function index(Request $request): Response
    {
        $level = (int) ($request->query->get('level') ?? AdminArea::LEVEL_COMMUNE);
        if (!in_array($level, [AdminArea::LEVEL_COMMUNE, AdminArea::LEVEL_DEPARTMENT], true)) {
            $level = AdminArea::LEVEL_COMMUNE;
        }

        return $this->render('coverage/index.html.twig', [
            'level' => $level,
            'global' => $this->roadSegments->globalCoverage(),
            'areas' => $this->roadSegments->coverageByAdminArea($level),
        ]);
    }

    #[Route('/coverage/geojson', name: 'app_coverage_geojson')]
    public function geojson(Request $request): JsonResponse
    {
        $minLat = (float) ($request->query->get('min_lat') ?? -90);
        $minLng = (float) ($request->query->get('min_lng') ?? -180);
        $maxLat = (float) ($request->query->get('max_lat') ?? 90);
        $maxLng = (float) ($request->query->get('max_lng') ?? 180);

        $rows = $this->roadSegments->findGeoJsonInBbox($minLat, $minLng, $maxLat, $maxLng);

        $features = [];
        foreach ($rows as $row) {
            $features[] = [
                'type' => 'Feature',
                'properties' => [
                    'id' => $row['id'],
                    'highway' => $row['highway'],
                    'name' => $row['name'],
                    'visited' => $row['visited'],
                ],
                'geometry' => json_decode($row['geometry'], true),
            ];
        }

        $response = new JsonResponse(['type' => 'FeatureCollection', 'features' => $features]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, max-age=300');

        return $response;
    }

    #[Route('/coverage/activity/{id}', name: 'app_coverage_activity')]
    public function activity(string $id): Response
    {
        $activity = $this->activityRepository->find($id);
        if (null === $activity) {
            throw $this->createNotFoundException();
        }

        $newM = $this->roadVisits->newKilometersForActivity($activity);

        return $this->render('coverage/activity.html.twig', [
            'activity' => $activity,
            'new_meters' => $newM,
        ]);
    }

    #[Route('/coverage/activity/{id}/geojson', name: 'app_coverage_activity_geojson')]
    public function activityGeoJson(string $id): JsonResponse
    {
        $activity = $this->activityRepository->find($id);
        if (null === $activity) {
            return new JsonResponse(['error' => 'activity not found'], 404);
        }

        $polyline = $activity->getSummaryPolyline();
        if (null === $polyline || '' === $polyline) {
            return new JsonResponse(['type' => 'FeatureCollection', 'features' => []]);
        }

        // Decode + bbox in PHP, ask DB for segments in that bbox annotated with
        // "newly visited by THIS activity".
        $decoder = new \App\Service\Map\PolylineDecoder();
        $points = $decoder->decode($polyline);
        if (count($points) < 2) {
            return new JsonResponse(['type' => 'FeatureCollection', 'features' => []]);
        }
        $minLat = $maxLat = $points[0][0];
        $minLng = $maxLng = $points[0][1];
        foreach ($points as [$lat, $lng]) {
            $minLat = min($minLat, $lat);
            $maxLat = max($maxLat, $lat);
            $minLng = min($minLng, $lng);
            $maxLng = max($maxLng, $lng);
        }

        $sql = <<<'SQL'
            SELECT r.id,
                   r.name,
                   r.highway,
                   ST_AsGeoJSON(ST_Simplify(r.geometry::geometry, 0.00005)) AS geometry,
                   v.first_activity_id = :activity_id AS first_here,
                   v.road_segment_id IS NOT NULL AS visited
            FROM road_segment r
            LEFT JOIN road_visit v ON v.road_segment_id = r.id
            WHERE r.geometry && ST_MakeEnvelope(:min_lng, :min_lat, :max_lng, :max_lat, 4326)::geography
        SQL;

        $rows = $this->connection->fetchAllAssociative($sql, [
            'activity_id' => $activity->getId(),
            'min_lat' => $minLat - 0.005,
            'min_lng' => $minLng - 0.005,
            'max_lat' => $maxLat + 0.005,
            'max_lng' => $maxLng + 0.005,
        ]);

        $features = [
            [
                'type' => 'Feature',
                'properties' => ['kind' => 'trace'],
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => array_map(static fn (array $p) => [$p[1], $p[0]], $points),
                ],
            ],
        ];
        foreach ($rows as $row) {
            $features[] = [
                'type' => 'Feature',
                'properties' => [
                    'id' => (string) $row['id'],
                    'name' => $row['name'],
                    'highway' => (string) $row['highway'],
                    'visited' => (bool) $row['visited'],
                    'first_here' => (bool) $row['first_here'],
                ],
                'geometry' => json_decode((string) $row['geometry'], true),
            ];
        }

        $response = new JsonResponse(['type' => 'FeatureCollection', 'features' => $features]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, max-age=300');

        return $response;
    }
}
