<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Athlete;
use App\Repository\AthleteRepository;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Handles the Strava OAuth callback (/connect/strava/check):
 * exchanges the authorization code for tokens, fetches the athlete
 * profile, enforces the mono-user restriction, and persists tokens.
 */
class StravaAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly AthleteRepository $athleteRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ?string $ownerAthleteId,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return 'connect_strava_check' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('strava');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge((string) $accessToken->getToken(), function () use ($client, $accessToken): Athlete {
                /** @var array{id?: int|string, firstname?: string, lastname?: string, profile?: string} $rawData */
                $rawData = $client->fetchUserFromToken($accessToken)->toArray();

                $stravaId = isset($rawData['id']) ? (string) $rawData['id'] : null;
                if (null === $stravaId || '' === $stravaId) {
                    throw new CustomUserMessageAuthenticationException('Strava did not return an athlete ID.');
                }

                $owner = $this->ownerAthleteId;
                if (null !== $owner && '' !== $owner && $owner !== $stravaId) {
                    throw new CustomUserMessageAuthenticationException('This account is not allowed to log in.');
                }

                // If no owner has been configured yet, but an athlete already exists,
                // refuse new athletes — first one wins.
                $existing = $this->athleteRepository->findOneByStravaId($stravaId);
                if (null === $existing && null !== $this->athleteRepository->findFirst()) {
                    throw new CustomUserMessageAuthenticationException('This is a single-user instance; another athlete is already connected.');
                }

                $athlete = $existing ?? new Athlete($stravaId);
                $athlete->setFirstName((string) ($rawData['firstname'] ?? ''));
                $athlete->setLastName((string) ($rawData['lastname'] ?? ''));
                $athlete->setProfilePictureUrl(isset($rawData['profile']) ? (string) $rawData['profile'] : null);

                $expiresAt = $this->resolveExpiry($accessToken);
                $athlete->updateTokens(
                    (string) $accessToken->getToken(),
                    (string) $accessToken->getRefreshToken(),
                    $expiresAt,
                );

                $this->athleteRepository->save($athlete, true);

                return $athlete;
            }),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $session = $request->getSession();
        if ($session instanceof \Symfony\Component\HttpFoundation\Session\Session) {
            $session->getFlashBag()->add('danger', $exception->getMessage());
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    private function resolveExpiry(AccessToken $token): \DateTimeImmutable
    {
        $expires = $token->getExpires();
        if (null !== $expires && $expires > 0) {
            return (new \DateTimeImmutable())->setTimestamp($expires);
        }

        return new \DateTimeImmutable('+6 hours');
    }
}
