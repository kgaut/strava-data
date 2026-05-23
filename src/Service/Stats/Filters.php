<?php

declare(strict_types=1);

namespace App\Service\Stats;

use Symfony\Component\HttpFoundation\Request;

/**
 * Read-only DTO collecting the global stats filters from the query string.
 */
final class Filters
{
    public function __construct(
        public readonly ?int $year = null,
        public readonly ?int $month = null,
        public readonly ?string $sportType = null,
        public readonly ?\DateTimeImmutable $from = null,
        public readonly ?\DateTimeImmutable $to = null,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $year = $request->query->get('year');
        $month = $request->query->get('month');
        $sport = $request->query->get('sport_type');
        $from = $request->query->get('from');
        $to = $request->query->get('to');

        return new self(
            year: is_numeric($year) ? (int) $year : null,
            month: is_numeric($month) ? (int) $month : null,
            sportType: is_string($sport) && '' !== $sport ? $sport : null,
            from: is_string($from) && '' !== $from ? self::parseDate($from) : null,
            to: is_string($to) && '' !== $to ? self::parseDate($to, endOfDay: true) : null,
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
}
