<?php

declare(strict_types=1);

namespace App\Service\Map\Background;

use App\Service\Map\MapRequest;

interface BackgroundRendererInterface
{
    /**
     * Paint the background on the given Imagick canvas, sized $width x $height,
     * representing the viewport [minLat, minLng, maxLat, maxLng] at the chosen zoom.
     */
    public function render(
        \Imagick $canvas,
        MapRequest $request,
        int $zoom,
        float $minLat,
        float $minLng,
        float $maxLat,
        float $maxLng,
    ): void;
}
