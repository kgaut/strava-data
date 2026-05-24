<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Athlete;
use App\Repository\ActivityRepository;
use App\Service\Stats\StatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class LiveMapController extends AbstractController
{
    public function __construct(
        private readonly ActivityRepository $activityRepository,
        private readonly StatsService $stats,
    ) {
    }

    #[Route('/map/live', name: 'app_map_live')]
    public function index(): Response
    {
        /** @var Athlete $athlete */
        $athlete = $this->getUser();

        return $this->render('map/live.html.twig', [
            'years' => $this->stats->years($athlete),
            'sport_types' => $this->stats->sportTypes($athlete),
        ]);
    }

    #[Route('/map/live/activities.json', name: 'app_map_live_activities')]
    public function activities(Request $request): JsonResponse
    {
        /** @var Athlete $athlete */
        $athlete = $this->getUser();
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
