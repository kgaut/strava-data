<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Stats\DistributionsService;
use App\Service\Stats\Filters;
use App\Service\Stats\StatsService;
use App\Service\Strava\AthleteProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DistributionsController extends AbstractController
{
    public function __construct(
        private readonly DistributionsService $distributions,
        private readonly StatsService $stats,
        private readonly AthleteProvider $athleteProvider,
    ) {
    }

    #[Route('/distributions', name: 'app_distributions')]
    public function index(Request $request): Response
    {
        $athlete = $this->athleteProvider->find();
        if (null === $athlete) {
            return $this->render('dashboard/empty.html.twig');
        }

        $filters = Filters::fromRequest($request);

        return $this->render('dashboard/distributions.html.twig', [
            'filters' => $filters,
            'years' => $this->stats->years($athlete),
            'sport_types' => $this->stats->sportTypes($athlete),
            'distance' => $this->distributions->distanceKmHistogram($athlete, $filters),
            'speed' => $this->distributions->speedKmhHistogram($athlete, $filters),
            'elevation' => $this->distributions->elevationHistogram($athlete, $filters),
        ]);
    }
}
