<?php

declare(strict_types=1);

namespace App\Tests\Service\Map;

use App\Service\Map\PolylineDecoder;
use PHPUnit\Framework\TestCase;

final class PolylineDecoderTest extends TestCase
{
    public function testDecodesGoogleReferencePolyline(): void
    {
        // Example from the Google Polyline Algorithm reference page.
        $encoded = '_p~iF~ps|U_ulLnnqC_mqNvxq`@';

        $decoder = new PolylineDecoder();
        $points = $decoder->decode($encoded);

        self::assertCount(3, $points);
        self::assertEqualsWithDelta(38.5, $points[0][0], 0.0001);
        self::assertEqualsWithDelta(-120.2, $points[0][1], 0.0001);
        self::assertEqualsWithDelta(40.7, $points[1][0], 0.0001);
        self::assertEqualsWithDelta(-120.95, $points[1][1], 0.0001);
        self::assertEqualsWithDelta(43.252, $points[2][0], 0.0001);
        self::assertEqualsWithDelta(-126.453, $points[2][1], 0.0001);
    }

    public function testEmptyPolylineYieldsEmptyArray(): void
    {
        self::assertSame([], (new PolylineDecoder())->decode(''));
    }
}
