<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Athlete;
use App\Service\Stats\Filters;
use App\Service\Stats\StatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractController
{
    public function __construct(private readonly StatsService $stats)
    {
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(Request $request): Response
    {
        /** @var Athlete $athlete */
        $athlete = $this->getUser();
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
        ]);
    }
}
