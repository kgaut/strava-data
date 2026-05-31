<?php

declare(strict_types=1);

namespace App\Tests\Service\Stats;

use App\Repository\ActivityRepository;
use App\Service\Stats\HeatmapService;
use PHPUnit\Framework\TestCase;

final class HeatmapServiceTest extends TestCase
{
    private function service(): HeatmapService
    {
        return new HeatmapService($this->createMock(ActivityRepository::class));
    }

    public function testStreaksWithGaps(): void
    {
        $dates = ['2025-01-01', '2025-01-02', '2025-01-03', '2025-01-05', '2025-01-06'];
        $stats = $this->service()->streaks($dates, new \DateTimeImmutable('2025-01-10'));

        self::assertSame(3, $stats['longest']);
        self::assertSame(0, $stats['current']);
        self::assertSame(5, $stats['total_active']);
    }

    public function testCurrentStreakIncludesToday(): void
    {
        $dates = ['2025-06-08', '2025-06-09', '2025-06-10'];
        $stats = $this->service()->streaks($dates, new \DateTimeImmutable('2025-06-10'));

        self::assertSame(3, $stats['current']);
        self::assertSame(3, $stats['longest']);
    }

    public function testCurrentStreakRollsBackOneDayWhenTodayIsEmpty(): void
    {
        $dates = ['2025-06-08', '2025-06-09'];
        $stats = $this->service()->streaks($dates, new \DateTimeImmutable('2025-06-10'));

        self::assertSame(2, $stats['current']);
    }

    public function testDuplicateDatesAreCollapsed(): void
    {
        $dates = ['2025-01-01', '2025-01-01', '2025-01-02'];
        $stats = $this->service()->streaks($dates, new \DateTimeImmutable('2025-01-05'));

        self::assertSame(2, $stats['total_active']);
        self::assertSame(2, $stats['longest']);
    }

    public function testEmptyInput(): void
    {
        $stats = $this->service()->streaks([], new \DateTimeImmutable('2025-06-10'));

        self::assertSame(['longest' => 0, 'current' => 0, 'total_active' => 0], $stats);
    }
}
