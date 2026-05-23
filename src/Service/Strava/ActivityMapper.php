<?php

declare(strict_types=1);

namespace App\Service\Strava;

use App\Entity\Activity;
use App\Entity\Athlete;

/**
 * Translates a raw Strava activity payload (as returned by /athlete/activities
 * or /activities/{id}) into an Activity entity, applying conservative defaults
 * for fields that may be missing or null.
 */
class ActivityMapper
{
    /**
     * @param array<string, mixed> $data
     */
    public function hydrate(Activity $activity, array $data): Activity
    {
        $activity->setName((string) ($data['name'] ?? ''));
        $activity->setType((string) ($data['type'] ?? ''));
        $activity->setSportType((string) ($data['sport_type'] ?? ($data['type'] ?? '')));
        $activity->setDistance((float) ($data['distance'] ?? 0));
        $activity->setMovingTime((int) ($data['moving_time'] ?? 0));
        $activity->setElapsedTime((int) ($data['elapsed_time'] ?? 0));
        $activity->setTotalElevationGain((float) ($data['total_elevation_gain'] ?? 0));

        if (isset($data['start_date'])) {
            $activity->setStartDate(new \DateTimeImmutable((string) $data['start_date']));
        }
        if (isset($data['start_date_local'])) {
            $activity->setStartDateLocal(new \DateTimeImmutable((string) $data['start_date_local']));
        }
        $activity->setTimezone(isset($data['timezone']) ? (string) $data['timezone'] : null);

        $activity->setAverageSpeed(isset($data['average_speed']) ? (float) $data['average_speed'] : null);
        $activity->setMaxSpeed(isset($data['max_speed']) ? (float) $data['max_speed'] : null);
        $activity->setAverageHeartrate(isset($data['average_heartrate']) ? (float) $data['average_heartrate'] : null);
        $activity->setMaxHeartrate(isset($data['max_heartrate']) ? (float) $data['max_heartrate'] : null);
        $activity->setHasHeartrate((bool) ($data['has_heartrate'] ?? false));
        $activity->setKudosCount((int) ($data['kudos_count'] ?? 0));
        $activity->setGearId(isset($data['gear_id']) ? (string) $data['gear_id'] : null);
        $activity->setTrainer((bool) ($data['trainer'] ?? false));
        $activity->setCommute((bool) ($data['commute'] ?? false));
        $activity->setManual((bool) ($data['manual'] ?? false));
        $activity->setPrivate((bool) ($data['private'] ?? false));

        // Polyline: prefer the high-resolution one when available (detail endpoint),
        // otherwise fall back to the summary polyline from the list endpoint.
        $polyline = null;
        if (isset($data['map']) && is_array($data['map'])) {
            $polyline = $data['map']['polyline'] ?? $data['map']['summary_polyline'] ?? null;
        }
        $activity->setSummaryPolyline(is_string($polyline) && '' !== $polyline ? $polyline : null);

        $activity->setStartLatlng($this->latlng($data['start_latlng'] ?? null));
        $activity->setEndLatlng($this->latlng($data['end_latlng'] ?? null));

        $activity->markImported();

        return $activity;
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function latlng(mixed $value): ?array
    {
        if (!is_array($value) || 2 !== count($value)) {
            return null;
        }
        $lat = (float) $value[0];
        $lng = (float) $value[1];
        if (0.0 === $lat && 0.0 === $lng) {
            return null;
        }

        return [$lat, $lng];
    }
}
