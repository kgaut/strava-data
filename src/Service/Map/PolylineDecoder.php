<?php

declare(strict_types=1);

namespace App\Service\Map;

/**
 * Decodes Google's encoded polyline format into a list of [lat, lng] pairs.
 *
 * Reference: https://developers.google.com/maps/documentation/utilities/polylinealgorithm
 */
final class PolylineDecoder
{
    /**
     * @return list<array{0: float, 1: float}> List of [lat, lng] pairs.
     */
    public function decode(string $encoded, int $precision = 5): array
    {
        $factor = 10 ** $precision;
        $length = strlen($encoded);
        $index = 0;
        $lat = 0;
        $lng = 0;
        $points = [];

        while ($index < $length) {
            // latitude
            [$dLat, $index] = $this->readSignedVarint($encoded, $index);
            $lat += $dLat;
            // longitude
            [$dLng, $index] = $this->readSignedVarint($encoded, $index);
            $lng += $dLng;

            $points[] = [$lat / $factor, $lng / $factor];
        }

        return $points;
    }

    /** @return array{0: int, 1: int} [value, newIndex] */
    private function readSignedVarint(string $encoded, int $index): array
    {
        $result = 0;
        $shift = 0;
        do {
            $byte = ord($encoded[$index]) - 63;
            ++$index;
            $result |= ($byte & 0x1F) << $shift;
            $shift += 5;
        } while ($byte >= 0x20);

        $value = ($result & 1) ? ~($result >> 1) : ($result >> 1);

        return [$value, $index];
    }
}
