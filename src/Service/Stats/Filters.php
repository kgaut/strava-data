<?php

declare(strict_types=1);

namespace App\Service\Stats;

use Symfony\Component\HttpFoundation\Request;

/**
 * Read-only DTO collecting the global stats filters from the query string.
 */
final class Filters
{
    /**
     * @param list<int> $daysOfWeek 0 = Sunday … 6 = Saturday (matches Postgres EXTRACT(DOW))
     */
    public function __construct(
        public readonly ?int $year = null,
        public readonly ?int $month = null,
        public readonly ?string $sportType = null,
        public readonly ?\DateTimeImmutable $from = null,
        public readonly ?\DateTimeImmutable $to = null,
        public readonly array $daysOfWeek = [],
        public readonly ?int $hourFrom = null,
        public readonly ?int $hourTo = null,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $year = $request->query->get('year');
        $month = $request->query->get('month');
        $sport = $request->query->get('sport_type');
        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $hourFrom = $request->query->get('hour_from');
        $hourTo = $request->query->get('hour_to');

        $rawDays = $request->query->all('day_of_week');
        $daysOfWeek = [];
        foreach ($rawDays as $value) {
            if (is_numeric($value)) {
                $d = (int) $value;
                if ($d >= 0 && $d <= 6) {
                    $daysOfWeek[] = $d;
                }
            }
        }
        $daysOfWeek = array_values(array_unique($daysOfWeek));

        return new self(
            year: is_numeric($year) ? (int) $year : null,
            month: is_numeric($month) ? (int) $month : null,
            sportType: is_string($sport) && '' !== $sport ? $sport : null,
            from: is_string($from) && '' !== $from ? self::parseDate($from) : null,
            to: is_string($to) && '' !== $to ? self::parseDate($to, endOfDay: true) : null,
            daysOfWeek: $daysOfWeek,
            hourFrom: self::parseHour($hourFrom),
            hourTo: self::parseHour($hourTo),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toRepositoryArray(): array
    {
        return [
            'year' => $this->year,
            'month' => $this->month,
            'sport_type' => $this->sportType,
            'from' => $this->from,
            'to' => $this->to,
            'days_of_week' => $this->daysOfWeek,
            'hour_from' => $this->hourFrom,
            'hour_to' => $this->hourTo,
        ];
    }

    private static function parseDate(string $value, bool $endOfDay = false): ?\DateTimeImmutable
    {
        try {
            $d = new \DateTimeImmutable($value);

            return $endOfDay ? $d->setTime(23, 59, 59) : $d->setTime(0, 0);
        } catch (\Exception) {
            return null;
        }
    }

    private static function parseHour(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }
        $h = (int) $value;

        return $h >= 0 && $h <= 23 ? $h : null;
    }
}
