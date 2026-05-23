<?php

declare(strict_types=1);

namespace App\OAuth;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

/**
 * Minimal Strava OAuth2 provider for league/oauth2-client.
 *
 * @see https://developers.strava.com/docs/authentication/
 */
final class StravaOAuthProvider extends AbstractProvider
{
    use BearerAuthorizationTrait;

    public function getBaseAuthorizationUrl(): string
    {
        return 'https://www.strava.com/oauth/authorize';
    }

    /**
     * @param array<string, mixed> $params
     */
    public function getBaseAccessTokenUrl(array $params): string
    {
        return 'https://www.strava.com/oauth/token';
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        return 'https://www.strava.com/api/v3/athlete';
    }

    /**
     * @return list<string>
     */
    protected function getDefaultScopes(): array
    {
        return ['read', 'activity:read_all', 'profile:read_all'];
    }

    protected function getScopeSeparator(): string
    {
        return ',';
    }

    /**
     * @param array<string, mixed>|string $data
     */
    protected function checkResponse(ResponseInterface $response, $data): void
    {
        if ($response->getStatusCode() >= 400) {
            $message = is_array($data) && isset($data['message']) ? (string) $data['message'] : $response->getReasonPhrase();
            throw new IdentityProviderException($message, $response->getStatusCode(), (string) $response->getBody());
        }
    }

    /**
     * @param array<string, mixed> $response
     */
    protected function createResourceOwner(array $response, AccessToken $token): ResourceOwnerInterface
    {
        return new StravaResourceOwner($response);
    }
}
