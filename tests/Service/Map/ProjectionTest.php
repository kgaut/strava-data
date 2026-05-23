<?php

declare(strict_types=1);

namespace App\Tests\Service\Map;

use App\Service\Map\Projection;
use PHPUnit\Framework\TestCase;

final class ProjectionTest extends TestCase
{
    public function testEquatorMapsToHalfWidthAtZoom0(): void
    {
        self::assertEqualsWithDelta(0.5, Projection::lonToTileX(0, 0), 1e-9);
        self::assertEqualsWithDelta(0.5, Projection::latToTileY(0, 0), 1e-9);
    }

    public function testRadiusBoundingBoxContainsCenter(): void
    {
        [$minLat, $minLng, $maxLat, $maxLng] = Projection::radiusBoundingBox(48.8566, 2.3522, 10_000);
        self::assertLessThan(48.8566, $minLat);
        self::assertGreaterThan(48.8566, $maxLat);
        self::assertLessThan(2.3522, $minLng);
        self::assertGreaterThan(2.3522, $maxLng);
    }

    public function testFitZoomShrinksAsAreaGrows(): void
    {
        $small = Projection::fitZoom(48.85, 2.34, 48.86, 2.36, 1024, 1024);
        $large = Projection::fitZoom(40.0, -10.0, 55.0, 15.0, 1024, 1024);
        self::assertGreaterThan($large, $small);
    }
}
