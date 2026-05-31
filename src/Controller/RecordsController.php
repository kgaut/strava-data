<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Stats\Filters;
use App\Service\Stats\RecordsService;
use App\Service\Stats\StatsService;
use App\Service\Strava\AthleteProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RecordsController extends AbstractController
{
    public function __construct(
        private readonly RecordsService $records,
        private readonly StatsService $stats,
        private readonly AthleteProvider $athleteProvider,
    ) {
    }

    #[Route('/records', name: 'app_records')]
    public function index(Request $request): Response
    {
        $athlete = $this->athleteProvider->find();
        if (null === $athlete) {
            return $this->render('dashboard/empty.html.twig');
        }

        $filters = Filters::fromRequest($request);

        return $this->render('dashboard/records.html.twig', [
            'filters' => $filters,
            'years' => $this->stats->years($athlete),
            'sport_types' => $this->stats->sportTypes($athlete),
            'longest_distance' => $this->records->longestByDistance($athlete, $filters),
            'longest_time' => $this->records->longestByTime($athlete, $filters),
            'highest_elevation' => $this->records->highestElevation($athlete, $filters),
            'fastest_avg_speed' => $this->records->fastestAvgSpeed($athlete, $filters),
            'highest_avg_hr' => $this->records->highestAvgHeartrate($athlete, $filters),
        ]);
    }
}
