<?php

declare(strict_types=1);

namespace App\Service\Strava;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin HTTP wrapper around the Strava REST API.
 * Pulls a fresh access token from TokenManager (cached in Redis) and
 * logs rate-limit headers for observability.
 */
class Client
{
    private const API_BASE = 'https://www.strava.com/api/v3';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly TokenManager $tokenManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActivities(?int $afterTimestamp = null, int $page = 1, int $perPage = 200): array
    {
        $params = ['page' => $page, 'per_page' => $perPage];
        if (null !== $afterTimestamp) {
            $params['after'] = $afterTimestamp;
        }

        /** @var list<array<string, mixed>> $data */
        $data = $this->request('GET', '/athlete/activities', ['query' => $params]);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getActivity(string $activityId): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->request('GET', "/activities/{$activityId}", ['query' => ['include_all_efforts' => 'false']]);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAthlete(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->request('GET', '/athlete');

        return $data;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<int|string, mixed>
     */
    private function request(string $method, string $path, array $options = []): array
    {
        $options['auth_bearer'] = $this->tokenManager->accessToken();

        try {
            $response = $this->httpClient->request($method, self::API_BASE.$path, $options);
            $headers = $response->getHeaders(false);
            $this->logRateLimit($headers);
            $status = $response->getStatusCode();
            if ($status >= 400) {
                throw new StravaApiException(sprintf('Strava API %s %s returned HTTP %d: %s', $method, $path, $status, $response->getContent(false)));
            }

            /** @var array<int|string, mixed> $payload */
            $payload = $response->toArray(false);

            return $payload;
        } catch (ClientException $e) {
            throw new StravaApiException('Strava API request failed: '.$e->getMessage(), 0, $e);
        }
    }

    /** @param array<string, array<int, string>> $headers */
    private function logRateLimit(array $headers): void
    {
        $usage = $headers['x-ratelimit-usage'][0] ?? null;
        $limit = $headers['x-ratelimit-limit'][0] ?? null;
        if (null !== $usage && null !== $limit) {
            $this->logger->debug('Strava rate-limit', ['usage' => $usage, 'limit' => $limit]);
        }
    }
}
