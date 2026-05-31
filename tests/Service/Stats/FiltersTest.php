<?php

declare(strict_types=1);

namespace App\Tests\Service\Stats;

use App\Service\Stats\Filters;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class FiltersTest extends TestCase
{
    public function testParsesYearMonthSportAndDates(): void
    {
        $request = new Request([
            'year' => '2025',
            'month' => '3',
            'sport_type' => 'Run',
            'from' => '2025-03-01',
            'to' => '2025-03-31',
        ]);
        $f = Filters::fromRequest($request);

        self::assertSame(2025, $f->year);
        self::assertSame(3, $f->month);
        self::assertSame('Run', $f->sportType);
        self::assertNotNull($f->from);
        self::assertNotNull($f->to);
        self::assertSame('2025-03-01 00:00:00', $f->from->format('Y-m-d H:i:s'));
        self::assertSame('2025-03-31 23:59:59', $f->to->format('Y-m-d H:i:s'));
    }

    public function testParsesDaysOfWeekAndDropsInvalid(): void
    {
        $request = new Request(['day_of_week' => ['1', '5', '9', 'oops', '5']]);
        $f = Filters::fromRequest($request);

        self::assertSame([1, 5], $f->daysOfWeek);
    }

    public function testParsesHourRangeWithWrapAround(): void
    {
        $request = new Request(['hour_from' => '22', 'hour_to' => '6']);
        $f = Filters::fromRequest($request);

        self::assertSame(22, $f->hourFrom);
        self::assertSame(6, $f->hourTo);
    }

    public function testRejectsOutOfRangeHour(): void
    {
        $request = new Request(['hour_from' => '24', 'hour_to' => '-1']);
        $f = Filters::fromRequest($request);

        self::assertNull($f->hourFrom);
        self::assertNull($f->hourTo);
    }

    public function testRepositoryArrayCarriesAllFields(): void
    {
        $f = new Filters(
            year: 2025,
            daysOfWeek: [0, 6],
            hourFrom: 7,
            hourTo: 9,
        );
        $arr = $f->toRepositoryArray();

        self::assertSame(2025, $arr['year']);
        self::assertSame([0, 6], $arr['days_of_week']);
        self::assertSame(7, $arr['hour_from']);
        self::assertSame(9, $arr['hour_to']);
    }
}
