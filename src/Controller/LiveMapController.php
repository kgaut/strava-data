<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ActivityRepository;
use App\Service\Stats\StatsService;
use App\Service\Strava\AthleteProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LiveMapController extends AbstractController
{
    public function __construct(
        private readonly ActivityRepository $activityRepository,
        private readonly StatsService $stats,
        private readonly AthleteProvider $athleteProvider,
    ) {
    }

    #[Route('/map/live', name: 'app_map_live')]
    public function index(): Response
    {
        $athlete = $this->athleteProvider->find();
        if (null === $athlete) {
            return $this->render('dashboard/empty.html.twig');
        }

        return $this->render('map/live.html.twig', [
            'years' => $this->stats->years($athlete),
            'sport_types' => $this->stats->sportTypes($athlete),
        ]);
    }

    #[Route('/map/live/activities.json', name: 'app_map_live_activities')]
    public function activities(Request $request): JsonResponse
    {
        $athlete = $this->athleteProvider->find();
        if (null === $athlete) {
            return new JsonResponse([]);
        }

        $filters = $this->readFilters($request);
        $rows = $this->activityRepository->findPolylinesForViewer($athlete, $filters);

        $response = new JsonResponse($rows);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, max-age=300');

        return $response;
    }

    /**
     * @return array{year: ?int, sport_type: ?string, from: ?\DateTimeImmutable, to: ?\DateTimeImmutable}
     */
    private function readFilters(Request $r): array
    {
        $year = $r->query->get('year');
        $sport = $r->query->get('sport_type');
        $from = $r->query->get('from');
        $to = $r->query->get('to');

        return [
            'year' => is_numeric($year) ? (int) $year : null,
            'sport_type' => is_string($sport) && '' !== $sport ? $sport : null,
            'from' => is_string($from) && '' !== $from ? new \DateTimeImmutable($from) : null,
            'to' => is_string($to) && '' !== $to ? new \DateTimeImmutable($to.' 23:59:59') : null,
        ];
    }
}
