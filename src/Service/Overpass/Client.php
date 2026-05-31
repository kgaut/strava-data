<?php

declare(strict_types=1);

namespace App\Service\Overpass;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin wrapper around the public Overpass API. Runs raw Overpass QL queries
 * and Nominatim lookups, throttled to respect both services' usage policies.
 *
 * @see https://wiki.openstreetmap.org/wiki/Overpass_API
 * @see https://operations.osmfoundation.org/policies/nominatim/
 */
class Client
{
    private const OVERPASS_URL = 'https://overpass-api.de/api/interpreter';
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/lookup';

    /** Nominatim policy: max 1 request per second. */
    private const NOMINATIM_MIN_INTERVAL_MICROS = 1_100_000;

    private float $lastNominatimAt = 0.0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $osmUserAgent,
    ) {
    }

    /**
     * Execute an Overpass QL query and return the decoded `elements` array.
     *
     * @return list<array<string, mixed>>
     */
    public function query(string $overpassQl, int $timeoutSeconds = 180): array
    {
        $this->logger->debug('Overpass query', ['ql' => $overpassQl]);
        $response = $this->httpClient->request('POST', self::OVERPASS_URL, [
            'body' => ['data' => $overpassQl],
            'headers' => ['User-Agent' => $this->osmUserAgent],
            'timeout' => $timeoutSeconds,
        ]);
        if (200 !== $response->getStatusCode()) {
            throw new OverpassException('Overpass returned HTTP '.$response->getStatusCode().': '.substr($response->getContent(false), 0, 500));
        }

        /** @var array{elements?: list<array<string, mixed>>} $data */
        $data = $response->toArray();

        return $data['elements'] ?? [];
    }

    /**
     * Look up an OSM relation/way/node by id and return its GeoJSON polygon
     * (or null if Nominatim doesn't return geometry).
     *
     * Throttled to ≤ 1 req / s per Nominatim policy.
     *
     * @return array<string, mixed>|null GeoJSON Geometry (Polygon or MultiPolygon)
     */
    public function nominatimGeometry(string $osmType, int $osmId): ?array
    {
        $this->throttleNominatim();

        $osmIdParam = strtoupper($osmType[0]).$osmId;
        $response = $this->httpClient->request('GET', self::NOMINATIM_URL, [
            'query' => [
                'osm_ids' => $osmIdParam,
                'format' => 'json',
                'polygon_geojson' => '1',
            ],
            'headers' => ['User-Agent' => $this->osmUserAgent],
            'timeout' => 30,
        ]);
        if (200 !== $response->getStatusCode()) {
            throw new OverpassException('Nominatim returned HTTP '.$response->getStatusCode());
        }

        /** @var list<array<string, mixed>> $data */
        $data = $response->toArray();
        if ([] === $data) {
            return null;
        }
        $entry = $data[0];
        $geom = $entry['geojson'] ?? null;
        if (!is_array($geom)) {
            return null;
        }

        return $geom;
    }

    private function throttleNominatim(): void
    {
        $now = microtime(true);
        $elapsed = ($now - $this->lastNominatimAt) * 1_000_000;
        if ($elapsed < self::NOMINATIM_MIN_INTERVAL_MICROS) {
            usleep((int) (self::NOMINATIM_MIN_INTERVAL_MICROS - $elapsed));
        }
        $this->lastNominatimAt = microtime(true);
    }
}
