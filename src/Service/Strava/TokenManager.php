<?php

declare(strict_types=1);

namespace App\Service\Strava;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Mints (and caches) Strava access tokens from a long-lived refresh token
 * stored in STRAVA_REFRESH_TOKEN. No per-user OAuth: the app is single-tenant
 * and the refresh token never expires unless the athlete revokes the
 * authorization in Strava settings.
 */
class TokenManager
{
    private const TOKEN_URL = 'https://www.strava.com/oauth/token';
    private const CACHE_KEY = 'strava.access_token';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheItemPoolInterface $cache,
        private readonly string $stravaClientId,
        private readonly string $stravaClientSecret,
        private readonly string $stravaRefreshToken,
    ) {
    }

    public function accessToken(): string
    {
        $item = $this->cache->getItem(self::CACHE_KEY);
        if ($item->isHit()) {
            /** @var string $cached */
            $cached = $item->get();

            return $cached;
        }

        if ('' === $this->stravaRefreshToken) {
            throw new StravaApiException('STRAVA_REFRESH_TOKEN is empty — set it in .env.local. See README for the one-shot bootstrap procedure.');
        }
        if ('' === $this->stravaClientId || '' === $this->stravaClientSecret) {
            throw new StravaApiException('STRAVA_CLIENT_ID and STRAVA_CLIENT_SECRET must be set.');
        }

        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => [
                'client_id' => $this->stravaClientId,
                'client_secret' => $this->stravaClientSecret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->stravaRefreshToken,
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new StravaApiException('Strava token refresh failed: HTTP '.$response->getStatusCode().' '.$response->getContent(false));
        }

        /** @var array{access_token: string, expires_at: int, refresh_token?: string} $data */
        $data = $response->toArray();

        $token = $data['access_token'];
        // Cache until 60s before expiry; default to 5h if the API ever omits it.
        $ttl = max(60, ($data['expires_at'] - time()) - 60);
        $item->set($token);
        $item->expiresAfter($ttl);
        $this->cache->save($item);

        return $token;
    }
}
