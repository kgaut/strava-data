<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Athlete;
use App\Service\Map\MapRenderer;
use App\Service\Map\MapRequest;
use App\Service\Stats\StatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class MapController extends AbstractController
{
    public function __construct(
        private readonly MapRenderer $renderer,
        private readonly StatsService $stats,
    ) {
    }

    #[Route('/map', name: 'app_map_form')]
    public function form(Request $request): Response
    {
        /** @var Athlete $athlete */
        $athlete = $this->getUser();

        $defaults = [
            'center_lat' => $request->query->get('center_lat', '48.8566'),
            'center_lng' => $request->query->get('center_lng', '2.3522'),
            'radius_km' => $request->query->get('radius_km', '20'),
            'background' => $request->query->get('background', MapRequest::BACKGROUND_TILES),
            'background_color' => $request->query->get('background_color', '#000000'),
            'trace_color' => $request->query->get('trace_color', '#fc4c02'),
            'trace_opacity' => $request->query->get('trace_opacity', '0.7'),
            'trace_width' => $request->query->get('trace_width', '2'),
            'width' => $request->query->get('width', '2048'),
            'height' => $request->query->get('height', '2048'),
            'from' => $request->query->get('from', ''),
            'to' => $request->query->get('to', ''),
            'sport_types' => $request->query->all('sport_types'),
        ];

        return $this->render('map/form.html.twig', [
            'defaults' => $defaults,
            'sport_types' => $this->stats->sportTypes($athlete),
        ]);
    }

    #[Route('/map/render', name: 'app_map_render')]
    public function renderImage(Request $request): Response
    {
        /** @var Athlete $athlete */
        $athlete = $this->getUser();
        $mapRequest = $this->buildRequest($request);
        $result = $this->renderer->render($athlete, $mapRequest);

        $response = new BinaryFileResponse($result['path']);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'strava-map.png');
        $response->headers->set('Content-Type', 'image/png');
        $response->headers->set('Cache-Control', 'private, max-age=3600');

        return $response;
    }

    private function buildRequest(Request $r): MapRequest
    {
        $sportTypes = array_values(array_filter(
            array_map('strval', $r->query->all('sport_types')),
            static fn (string $s): bool => '' !== $s,
        ));

        $from = $r->query->get('from');
        $to = $r->query->get('to');

        return new MapRequest(
            centerLat: (float) ($r->query->get('center_lat') ?? 0),
            centerLng: (float) ($r->query->get('center_lng') ?? 0),
            radiusMeters: max(100.0, ((float) ($r->query->get('radius_km') ?? 10)) * 1000.0),
            background: MapRequest::BACKGROUND_BLANK === $r->query->get('background', MapRequest::BACKGROUND_TILES)
                ? MapRequest::BACKGROUND_BLANK
                : MapRequest::BACKGROUND_TILES,
            backgroundColor: (string) $r->query->get('background_color', '#000000'),
            traceColor: (string) $r->query->get('trace_color', '#fc4c02'),
            traceOpacity: max(0.0, min(1.0, (float) ($r->query->get('trace_opacity') ?? 0.7))),
            traceWidth: max(1, min(20, (int) ($r->query->get('trace_width') ?? 2))),
            width: max(256, min(4096, (int) ($r->query->get('width') ?? 2048))),
            height: max(256, min(4096, (int) ($r->query->get('height') ?? 2048))),
            from: is_string($from) && '' !== $from ? new \DateTimeImmutable($from) : null,
            to: is_string($to) && '' !== $to ? new \DateTimeImmutable($to.' 23:59:59') : null,
            sportTypes: $sportTypes,
        );
    }
}
