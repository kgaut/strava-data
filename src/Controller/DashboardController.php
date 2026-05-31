<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ActivityRepository;
use App\Repository\RoadSegmentRepository;
use App\Service\Stats\Filters;
use App\Service\Stats\StatsService;
use App\Service\Strava\AthleteProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly StatsService $stats,
        private readonly AthleteProvider $athleteProvider,
        private readonly ActivityRepository $activityRepository,
        private readonly RoadSegmentRepository $roadSegmentRepository,
    ) {
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(Request $request): Response
    {
        $athlete = $this->athleteProvider->find();
        if (null === $athlete) {
            return $this->render('dashboard/empty.html.twig');
        }

        $filters = Filters::fromRequest($request);
        $data = $this->stats->overview($athlete, $filters);

        return $this->render('dashboard/overview.html.twig', [
            'athlete' => $athlete,
            'filters' => $filters,
            'years' => $this->stats->years($athlete),
            'sport_types' => $this->stats->sportTypes($athlete),
            'totals' => $data['totals'],
            'by_year' => $data['by_year'],
            'by_month' => $data['by_month'],
            'by_sport' => $data['by_sport'],
            'cumulative_by_year' => $this->activityRepository->cumulativeByYear($athlete),
            'coverage' => $this->roadSegmentRepository->globalCoverage(),
        ]);
    }
}
