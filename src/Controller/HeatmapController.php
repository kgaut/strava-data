<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Stats\Filters;
use App\Service\Stats\HeatmapService;
use App\Service\Stats\StatsService;
use App\Service\Strava\AthleteProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HeatmapController extends AbstractController
{
    public function __construct(
        private readonly HeatmapService $heatmap,
        private readonly StatsService $stats,
        private readonly AthleteProvider $athleteProvider,
    ) {
    }

    #[Route('/heatmap', name: 'app_heatmap')]
    public function index(Request $request): Response
    {
        $athlete = $this->athleteProvider->find();
        if (null === $athlete) {
            return $this->render('dashboard/empty.html.twig');
        }

        $year = (int) ($request->query->get('year') ?? (new \DateTimeImmutable())->format('Y'));
        // Force the year on the filters so the rest of the global filters still apply.
        $request->query->set('year', (string) $year);
        $filters = Filters::fromRequest($request);

        $daily = $this->heatmap->dailyVolume($athlete, $filters);
        // Streaks ignore the year filter — they're a lifetime view.
        $lifetimeDaily = $this->heatmap->dailyVolume($athlete, new Filters());
        $streaks = $this->heatmap->streaks(array_keys($lifetimeDaily));

        // Pre-bucket distance for colour scale (5 levels).
        $maxKm = 0.0;
        foreach ($daily as $d) {
            $maxKm = max($maxKm, $d['distance_m'] / 1000.0);
        }

        return $this->render('dashboard/heatmap.html.twig', [
            'filters' => $filters,
            'year' => $year,
            'years' => $this->stats->years($athlete),
            'sport_types' => $this->stats->sportTypes($athlete),
            'daily' => $daily,
            'streaks' => $streaks,
            'max_km' => $maxKm,
        ]);
    }
}
