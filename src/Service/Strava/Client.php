<?php

declare(strict_types=1);

namespace App\Service\Strava;

use App\Entity\Athlete;
use App\Repository\AthleteRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin HTTP wrapper around the Strava REST API.
 * Refreshes the access token automatically when it is about to expire,
 * and surfaces rate-limit headers via the LoggerInterface for observability.
 */
class Client
{
    private const API_BASE = 'https://www.strava.com/api/v3';
    private const TOKEN_URL = 'https://www.strava.com/oauth/token';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AthleteRepository $athleteRepository,
        private readonly LoggerInterface $logger,
        private readonly string $stravaClientId,
        private readonly string $stravaClientSecret,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActivities(Athlete $athlete, ?int $afterTimestamp = null, int $page = 1, int $perPage = 200): array
    {
        $params = ['page' => $page, 'per_page' => $perPage];
        if (null !== $afterTimestamp) {
            $params['after'] = $afterTimestamp;
        }

        /** @var list<array<string, mixed>> $data */
        $data = $this->request($athlete, 'GET', '/athlete/activities', ['query' => $params]);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getActivity(Athlete $athlete, string $activityId): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->request($athlete, 'GET', "/activities/{$activityId}", ['query' => ['include_all_efforts' => 'false']]);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAthlete(Athlete $athlete): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->request($athlete, 'GET', '/athlete');

        return $data;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<int|string, mixed>
     */
    private function request(Athlete $athlete, string $method, string $path, array $options = []): array
    {
        $this->ensureFreshToken($athlete);

        $options['auth_bearer'] = $athlete->getAccessToken();

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

    private function ensureFreshToken(Athlete $athlete): void
    {
        if (!$athlete->isTokenExpired()) {
            return;
        }

        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => [
                'client_id' => $this->stravaClientId,
                'client_secret' => $this->stravaClientSecret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $athlete->getRefreshToken(),
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new StravaApiException('Failed to refresh Strava token: HTTP '.$response->getStatusCode().' '.$response->getContent(false));
        }

        /** @var array{access_token: string, refresh_token: string, expires_at: int} $data */
        $data = $response->toArray();

        $athlete->updateTokens(
            $data['access_token'],
            $data['refresh_token'],
            (new \DateTimeImmutable())->setTimestamp($data['expires_at']),
        );
        $this->athleteRepository->save($athlete, true);
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
