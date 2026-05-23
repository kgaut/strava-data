<?php

declare(strict_types=1);

namespace App\Service\Map;

/**
 * Web Mercator (EPSG:3857) projection helpers expressed in "tile coordinates":
 * one unit = one OSM-style 256px tile at the requested zoom level.
 *
 * Pixel coordinates are obtained by multiplying tile coordinates by 256.
 */
final class Projection
{
    public const TILE_SIZE = 256;

    public static function lonToTileX(float $lon, int $zoom): float
    {
        return ($lon + 180.0) / 360.0 * (2 ** $zoom);
    }

    public static function latToTileY(float $lat, int $zoom): float
    {
        $latRad = deg2rad($lat);

        return (1.0 - log(tan($latRad) + 1.0 / cos($latRad)) / \M_PI) / 2.0 * (2 ** $zoom);
    }

    /**
     * Pick the largest zoom level for which the given bbox fits inside the canvas.
     *
     * @return int Zoom level in [1, 19]
     */
    public static function fitZoom(
        float $minLat,
        float $minLng,
        float $maxLat,
        float $maxLng,
        int $widthPx,
        int $heightPx,
        int $padding = 32,
    ): int {
        $availW = max(1, $widthPx - 2 * $padding);
        $availH = max(1, $heightPx - 2 * $padding);

        for ($zoom = 19; $zoom >= 1; --$zoom) {
            $x1 = self::lonToTileX($minLng, $zoom) * self::TILE_SIZE;
            $x2 = self::lonToTileX($maxLng, $zoom) * self::TILE_SIZE;
            $y1 = self::latToTileY($maxLat, $zoom) * self::TILE_SIZE;
            $y2 = self::latToTileY($minLat, $zoom) * self::TILE_SIZE;

            if (abs($x2 - $x1) <= $availW && abs($y2 - $y1) <= $availH) {
                return $zoom;
            }
        }

        return 1;
    }

    /**
     * Approximate bounding box around a center within a given radius in meters.
     *
     * @return array{0: float, 1: float, 2: float, 3: float} [minLat, minLng, maxLat, maxLng]
     */
    public static function radiusBoundingBox(float $centerLat, float $centerLng, float $radiusMeters): array
    {
        $latDelta = $radiusMeters / 111_320.0;
        $lngDelta = $radiusMeters / (111_320.0 * cos(deg2rad($centerLat)));

        return [
            $centerLat - $latDelta,
            $centerLng - $lngDelta,
            $centerLat + $latDelta,
            $centerLng + $lngDelta,
        ];
    }
}
