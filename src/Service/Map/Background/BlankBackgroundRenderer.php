<?php

declare(strict_types=1);

namespace App\Service\Map\Background;

use App\Service\Map\MapRequest;

/**
 * Fills the canvas with a solid colour (or leaves it transparent if the user
 * picks #00000000). No external dependency or network call.
 */
final class BlankBackgroundRenderer implements BackgroundRendererInterface
{
    public function render(
        \Imagick $canvas,
        MapRequest $request,
        int $zoom,
        float $minLat,
        float $minLng,
        float $maxLat,
        float $maxLng,
    ): void {
        $color = $request->backgroundColor;
        $pixel = new \ImagickPixel($color);
        $draw = new \ImagickDraw();
        $draw->setFillColor($pixel);
        $draw->rectangle(0, 0, $canvas->getImageWidth(), $canvas->getImageHeight());
        $canvas->drawImage($draw);
        $draw->clear();
        $pixel->clear();
    }
}
