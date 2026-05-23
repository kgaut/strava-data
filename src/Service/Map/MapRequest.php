<?php

declare(strict_types=1);

namespace App\Service\Map;

/**
 * Immutable request DTO consumed by MapRenderer.
 */
final class MapRequest
{
    public const BACKGROUND_TILES = 'tiles';
    public const BACKGROUND_BLANK = 'blank';

    /**
     * @param self::BACKGROUND_*    $background
     * @param list<string>          $sportTypes
     */
    public function __construct(
        public readonly float $centerLat,
        public readonly float $centerLng,
        public readonly float $radiusMeters,
        public readonly string $background = self::BACKGROUND_TILES,
        public readonly string $backgroundColor = '#000000',
        public readonly string $traceColor = '#fc4c02',
        public readonly float $traceOpacity = 0.7,
        public readonly int $traceWidth = 2,
        public readonly int $width = 2048,
        public readonly int $height = 2048,
        public readonly ?\DateTimeImmutable $from = null,
        public readonly ?\DateTimeImmutable $to = null,
        public readonly array $sportTypes = [],
    ) {
    }

    public function fingerprint(): string
    {
        return hash('sha256', serialize([
            $this->centerLat, $this->centerLng, $this->radiusMeters,
            $this->background, $this->backgroundColor, $this->traceColor,
            $this->traceOpacity, $this->traceWidth, $this->width, $this->height,
            $this->from?->format('c'), $this->to?->format('c'), $this->sportTypes,
        ]));
    }
}
