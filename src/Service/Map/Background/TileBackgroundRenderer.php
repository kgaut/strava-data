<?php

declare(strict_types=1);

namespace App\Service\Map\Background;

use App\Service\Map\MapRequest;
use App\Service\Map\Projection;
use App\Service\Map\TileFetcher;

/**
 * Paints the OSM tile mosaic covering the requested viewport on the canvas.
 *
 * The canvas pixel (0, 0) corresponds to (maxLat, minLng) of the viewport.
 * Tiles overlapping the viewport are downloaded (or read from cache), then
 * each tile is composited onto the canvas at its computed pixel offset.
 */
final class TileBackgroundRenderer implements BackgroundRendererInterface
{
    public function __construct(private readonly TileFetcher $tileFetcher)
    {
    }

    public function render(
        \Imagick $canvas,
        MapRequest $request,
        int $zoom,
        float $minLat,
        float $minLng,
        float $maxLat,
        float $maxLng,
    ): void {
        $tileSize = Projection::TILE_SIZE;

        // Tile (fractional) coordinates of the top-left corner of the viewport.
        $topLeftX = Projection::lonToTileX($minLng, $zoom);
        $topLeftY = Projection::latToTileY($maxLat, $zoom);
        $bottomRightX = Projection::lonToTileX($maxLng, $zoom);
        $bottomRightY = Projection::latToTileY($minLat, $zoom);

        // The viewport is centered on the canvas. Compute the pixel offset
        // between viewport top-left and canvas top-left.
        $viewportWidthPx = ($bottomRightX - $topLeftX) * $tileSize;
        $viewportHeightPx = ($bottomRightY - $topLeftY) * $tileSize;
        $offsetX = (int) round(($canvas->getImageWidth() - $viewportWidthPx) / 2);
        $offsetY = (int) round(($canvas->getImageHeight() - $viewportHeightPx) / 2);

        $tileXMin = (int) floor($topLeftX);
        $tileXMax = (int) floor($bottomRightX);
        $tileYMin = (int) floor($topLeftY);
        $tileYMax = (int) floor($bottomRightY);

        for ($tx = $tileXMin; $tx <= $tileXMax; ++$tx) {
            for ($ty = $tileYMin; $ty <= $tileYMax; ++$ty) {
                $bytes = $this->tileFetcher->fetch($zoom, $tx, $ty);
                $tile = new \Imagick();
                try {
                    $tile->readImageBlob($bytes);
                } catch (\ImagickException) {
                    $tile->clear();
                    continue;
                }
                $px = (int) round($offsetX + ($tx - $topLeftX) * $tileSize);
                $py = (int) round($offsetY + ($ty - $topLeftY) * $tileSize);
                $canvas->compositeImage($tile, \Imagick::COMPOSITE_OVER, $px, $py);
                $tile->clear();
            }
        }
    }
}
