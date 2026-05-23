<?php

declare(strict_types=1);

namespace App\Tests\Service\Strava;

use App\Entity\Activity;
use App\Entity\Athlete;
use App\Service\Strava\ActivityMapper;
use PHPUnit\Framework\TestCase;

final class ActivityMapperTest extends TestCase
{
    public function testHydratesAllFieldsFromStravaPayload(): void
    {
        $athlete = new Athlete('999');
        $activity = new Activity('12345', $athlete);

        $payload = [
            'id' => 12345,
            'name' => 'Morning Run',
            'type' => 'Run',
            'sport_type' => 'TrailRun',
            'distance' => 10250.4,
            'moving_time' => 3120,
            'elapsed_time' => 3250,
            'total_elevation_gain' => 180.5,
            'start_date' => '2026-01-15T07:30:00Z',
            'start_date_local' => '2026-01-15T08:30:00Z',
            'timezone' => '(GMT+01:00) Europe/Paris',
            'average_speed' => 3.28,
            'max_speed' => 4.5,
            'average_heartrate' => 152.0,
            'max_heartrate' => 180.0,
            'has_heartrate' => true,
            'kudos_count' => 7,
            'gear_id' => 'g123',
            'trainer' => false,
            'commute' => false,
            'manual' => false,
            'private' => false,
            'map' => [
                'summary_polyline' => '_p~iF~ps|U_ulLnnqC',
            ],
            'start_latlng' => [48.8566, 2.3522],
            'end_latlng' => [48.8580, 2.3530],
        ];

        (new ActivityMapper())->hydrate($activity, $payload);

        self::assertSame('Morning Run', $activity->getName());
        self::assertSame('Run', $activity->getType());
        self::assertSame('TrailRun', $activity->getSportType());
        self::assertEqualsWithDelta(10250.4, $activity->getDistance(), 0.001);
        self::assertSame(3120, $activity->getMovingTime());
        self::assertSame(180, (int) $activity->getTotalElevationGain());
        self::assertTrue($activity->hasHeartrate());
        self::assertSame(7, $activity->getKudosCount());
        self::assertNotNull($activity->getSummaryPolyline());
        $startLatlng = $activity->getStartLatlng();
        self::assertNotNull($startLatlng);
        self::assertStringContainsString('POINT', $startLatlng);
    }

    public function testEmptyLatlngIsTreatedAsNull(): void
    {
        $athlete = new Athlete('1');
        $activity = new Activity('1', $athlete);

        (new ActivityMapper())->hydrate($activity, [
            'name' => 'Indoor',
            'type' => 'VirtualRide',
            'sport_type' => 'VirtualRide',
            'distance' => 30000,
            'moving_time' => 3600,
            'elapsed_time' => 3600,
            'total_elevation_gain' => 0,
            'start_date' => '2026-02-01T10:00:00Z',
            'start_date_local' => '2026-02-01T11:00:00Z',
            'has_heartrate' => false,
            'kudos_count' => 0,
            'trainer' => true,
            'commute' => false,
            'manual' => false,
            'private' => false,
            'start_latlng' => [],
            'end_latlng' => [0.0, 0.0],
        ]);

        self::assertNull($activity->getStartLatlng());
        self::assertNull($activity->getEndLatlng());
    }
}
