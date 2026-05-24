<?php

declare(strict_types=1);

namespace App\Service\Strava;

use App\Entity\Athlete;
use App\Repository\AthleteRepository;

/**
 * Resolves the single athlete this instance is tied to. Lazily bootstraps
 * the Athlete entity from /athlete the first time we have a working token
 * but no row in DB.
 */
class AthleteProvider
{
    public function __construct(
        private readonly AthleteRepository $athleteRepository,
        private readonly Client $client,
    ) {
    }

    /**
     * Return the athlete if one exists in DB. Read-only path used by web
     * controllers — won't hit Strava.
     */
    public function find(): ?Athlete
    {
        return $this->athleteRepository->findFirst();
    }

    /**
     * Get the athlete, creating it from the Strava /athlete endpoint if it
     * does not exist yet. Used by sync commands.
     */
    public function getOrBootstrap(): Athlete
    {
        $athlete = $this->athleteRepository->findFirst();
        if (null !== $athlete) {
            return $athlete;
        }

        $profile = $this->client->getAthlete();
        $stravaId = isset($profile['id']) ? (string) $profile['id'] : '';
        if ('' === $stravaId) {
            throw new StravaApiException('Strava /athlete returned no ID — check that the refresh token is valid.');
        }

        $athlete = new Athlete($stravaId);
        $athlete->setFirstName((string) ($profile['firstname'] ?? ''));
        $athlete->setLastName((string) ($profile['lastname'] ?? ''));
        $athlete->setProfilePictureUrl(isset($profile['profile']) ? (string) $profile['profile'] : null);
        $this->athleteRepository->save($athlete, true);

        return $athlete;
    }
}
