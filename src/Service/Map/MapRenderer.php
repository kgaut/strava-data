<?php

declare(strict_types=1);

namespace App\Service\Map;

use App\Entity\Athlete;
use App\Repository\ActivityRepository;
use App\Service\Map\Background\BackgroundRendererInterface;
use App\Service\Map\Background\BlankBackgroundRenderer;
use App\Service\Map\Background\TileBackgroundRenderer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Renders a PNG with all matching activity polylines overlaid, optionally on
 * top of OSM tiles. Results are cached on disk keyed by the request fingerprint.
 */
class MapRenderer
{
    public function __construct(
        private readonly ActivityRepository $activityRepository,
        private readonly PolylineDecoder $polylineDecoder,
        private readonly TileBackgroundRenderer $tileBackground,
        private readonly BlankBackgroundRenderer $blankBackground,
        private readonly Filesystem $filesystem,
        private readonly LoggerInterface $logger,
        private readonly string $mapCacheDir,
    ) {
    }

    /**
     * @return array{path: string, count: int, zoom: int}
     */
    public function render(Athlete $athlete, MapRequest $request): array
    {
        $path = sprintf('%s/%s.png', rtrim($this->mapCacheDir, '/'), $request->fingerprint());
        if (is_file($path)) {
            $this->logger->debug('Map cache hit', ['path' => $path]);

            return ['path' => $path, 'count' => -1, 'zoom' => 0];
        }

        [$minLat, $minLng, $maxLat, $maxLng] = Projection::radiusBoundingBox(
            $request->centerLat,
            $request->centerLng,
            $request->radiusMeters,
        );
        $zoom = Projection::fitZoom($minLat, $minLng, $maxLat, $maxLng, $request->width, $request->height);

        $canvas = new \Imagick();
        $canvas->newImage($request->width, $request->height, new \ImagickPixel('transparent'), 'png');
        $canvas->setImageFormat('png');

        $background = $this->pickBackground($request->background);
        $background->render($canvas, $request, $zoom, $minLat, $minLng, $maxLat, $maxLng);

        $activities = $this->activityRepository->findInRadius(
            $athlete,
            $request->centerLat,
            $request->centerLng,
            $request->radiusMeters,
            [
                'from' => $request->from,
                'to' => $request->to,
                'sport_types' => $request->sportTypes,
            ],
        );

        $this->drawPolylines($canvas, $request, $activities, $zoom, $minLat, $maxLat, $minLng, $maxLng);

        $this->filesystem->mkdir(dirname($path));
        $canvas->writeImage($path);
        $canvas->clear();

        return ['path' => $path, 'count' => count($activities), 'zoom' => $zoom];
    }

    /** @param list<\App\Entity\Activity> $activities */
    private function drawPolylines(
        \Imagick $canvas,
        MapRequest $request,
        array $activities,
        int $zoom,
        float $minLat,
        float $maxLat,
        float $minLng,
        float $maxLng,
    ): void {
        $tileSize = Projection::TILE_SIZE;
        $topLeftX = Projection::lonToTileX($minLng, $zoom);
        $topLeftY = Projection::latToTileY($maxLat, $zoom);
        $bottomRightX = Projection::lonToTileX($maxLng, $zoom);
        $bottomRightY = Projection::latToTileY($minLat, $zoom);
        $viewportWidthPx = ($bottomRightX - $topLeftX) * $tileSize;
        $viewportHeightPx = ($bottomRightY - $topLeftY) * $tileSize;
        $offsetX = ($canvas->getImageWidth() - $viewportWidthPx) / 2;
        $offsetY = ($canvas->getImageHeight() - $viewportHeightPx) / 2;

        $rgba = $this->parseHexColor($request->traceColor, $request->traceOpacity);

        $draw = new \ImagickDraw();
        $draw->setStrokeColor(new \ImagickPixel(sprintf('rgba(%d,%d,%d,%.2f)', $rgba[0], $rgba[1], $rgba[2], $rgba[3])));
        $draw->setStrokeWidth($request->traceWidth);
        $draw->setFillOpacity(0);
        $draw->setStrokeAntialias(true);
        $draw->setStrokeLineCap(\Imagick::LINECAP_ROUND);
        $draw->setStrokeLineJoin(\Imagick::LINEJOIN_ROUND);

        $drewSomething = false;
        foreach ($activities as $activity) {
            $polyline = $activity->getSummaryPolyline();
            if (null === $polyline || '' === $polyline) {
                continue;
            }
            $points = $this->polylineDecoder->decode($polyline);
            if (count($points) < 2) {
                continue;
            }

            $segment = [];
            foreach ($points as [$lat, $lng]) {
                $px = $offsetX + (Projection::lonToTileX($lng, $zoom) - $topLeftX) * $tileSize;
                $py = $offsetY + (Projection::latToTileY($lat, $zoom) - $topLeftY) * $tileSize;
                $segment[] = ['x' => $px, 'y' => $py];
            }
            $draw->polyline($segment);
            $drewSomething = true;
        }

        if ($drewSomething) {
            $canvas->drawImage($draw);
        }
        $draw->clear();
    }

    private function pickBackground(string $kind): BackgroundRendererInterface
    {
        return match ($kind) {
            MapRequest::BACKGROUND_BLANK => $this->blankBackground,
            default => $this->tileBackground,
        };
    }

    /** @return array{0: int, 1: int, 2: int, 3: float} */
    private function parseHexColor(string $hex, float $opacity): array
    {
        $hex = ltrim($hex, '#');
        if (3 === strlen($hex)) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (6 !== strlen($hex)) {
            return [252, 76, 2, max(0, min(1, $opacity))];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
            max(0.0, min(1.0, $opacity)),
        ];
    }
}
