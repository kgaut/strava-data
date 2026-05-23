<?php

declare(strict_types=1);

namespace App\Service\Map;

use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches OSM (or compatible) tiles with aggressive disk caching.
 *
 * Respects the OSM tile usage policy:
 * - identifies itself with a configured User-Agent
 * - caches every tile locally to disk, so re-renders don't hit the upstream
 * - throttles requests to ~2/s when the cache is cold
 *
 * @see https://operations.osmfoundation.org/policies/tiles/
 */
class TileFetcher
{
    private const MIN_INTERVAL_MICROSECONDS = 500_000; // 2 req/s

    private float $lastRequestAt = 0.0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly Filesystem $filesystem,
        private readonly string $osmUserAgent,
        private readonly string $osmTileUrlTemplate,
        private readonly string $tileCacheDir,
    ) {
    }

    /**
     * Fetches the tile at (z, x, y), returning raw PNG bytes (from cache or network).
     */
    public function fetch(int $z, int $x, int $y): string
    {
        $path = sprintf('%s/%d/%d/%d.png', rtrim($this->tileCacheDir, '/'), $z, $x, $y);
        if (is_file($path)) {
            $contents = file_get_contents($path);
            if (false !== $contents) {
                return $contents;
            }
        }

        $this->throttle();

        $url = strtr($this->osmTileUrlTemplate, [
            '{z}' => (string) $z,
            '{x}' => (string) $x,
            '{y}' => (string) $y,
        ]);

        $response = $this->httpClient->request('GET', $url, [
            'headers' => ['User-Agent' => $this->osmUserAgent],
            'timeout' => 10,
        ]);
        if (200 !== $response->getStatusCode()) {
            $this->logger->warning('Tile fetch failed', ['url' => $url, 'status' => $response->getStatusCode()]);

            // Return a blank tile rather than aborting the whole render.
            return $this->blankTile();
        }

        $bytes = $response->getContent();
        $this->filesystem->mkdir(dirname($path));
        file_put_contents($path, $bytes);

        return $bytes;
    }

    private function throttle(): void
    {
        $now = microtime(true);
        $elapsed = ($now - $this->lastRequestAt) * 1_000_000;
        if ($elapsed < self::MIN_INTERVAL_MICROSECONDS) {
            usleep((int) (self::MIN_INTERVAL_MICROSECONDS - $elapsed));
        }
        $this->lastRequestAt = microtime(true);
    }

    private function blankTile(): string
    {
        $img = new \Imagick();
        $img->newImage(Projection::TILE_SIZE, Projection::TILE_SIZE, new \ImagickPixel('#cccccc'));
        $img->setImageFormat('png');
        $bytes = $img->getImageBlob();
        $img->clear();

        return $bytes;
    }
}
