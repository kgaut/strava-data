<?php

declare(strict_types=1);

namespace App\Service\Stats;

use App\Entity\Athlete;
use App\Repository\ActivityRepository;

/**
 * Wraps ActivityRepository aggregates into a single facade used by the dashboard.
 */
class StatsService
{
    public function __construct(private readonly ActivityRepository $activityRepository)
    {
    }

    /**
     * @return array{
     *     totals: array{count: int, distance_m: float, moving_time_s: int, elevation_m: float},
     *     by_year: list<array{year: int, count: int, distance_m: float, moving_time_s: int, elevation_m: float}>,
     *     by_month: list<array{year: int, month: int, count: int, distance_m: float, moving_time_s: int}>,
     *     by_sport: list<array{sport_type: string, count: int, distance_m: float, moving_time_s: int}>,
     * }
     */
    public function overview(Athlete $athlete, Filters $filters): array
    {
        $params = $filters->toRepositoryArray();

        return [
            'totals' => $this->activityRepository->totals($athlete, $params),
            'by_year' => $this->activityRepository->totalsByYear($athlete, $params),
            'by_month' => $this->activityRepository->totalsByMonth($athlete, $params),
            'by_sport' => $this->activityRepository->totalsBySportType($athlete, $params),
        ];
    }

    /**
     * @return list<int>
     */
    public function years(Athlete $athlete): array
    {
        return $this->activityRepository->listYears($athlete);
    }

    /**
     * @return list<string>
     */
    public function sportTypes(Athlete $athlete): array
    {
        return $this->activityRepository->listSportTypes($athlete);
    }
}
