<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Jsor\Doctrine\PostGIS\Types\PostGISType;

#[ORM\Entity(repositoryClass: ActivityRepository::class)]
#[ORM\Table(name: 'activity')]
#[ORM\Index(name: 'activity_start_date_idx', columns: ['start_date'])]
#[ORM\Index(name: 'activity_sport_type_idx', columns: ['sport_type'])]
class Activity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::BIGINT)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Athlete::class, inversedBy: 'activities')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Athlete $athlete;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 50)]
    private string $type = '';

    #[ORM\Column(length: 50)]
    private string $sportType = '';

    /** Distance in meters. */
    #[ORM\Column(type: Types::FLOAT)]
    private float $distance = 0.0;

    /** Moving time in seconds. */
    #[ORM\Column(type: Types::INTEGER)]
    private int $movingTime = 0;

    /** Elapsed time in seconds. */
    #[ORM\Column(type: Types::INTEGER)]
    private int $elapsedTime = 0;

    /** Total elevation gain in meters. */
    #[ORM\Column(type: Types::FLOAT)]
    private float $totalElevationGain = 0.0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $startDateLocal;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $timezone = null;

    /** Average speed in m/s. */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $averageSpeed = null;

    /** Max speed in m/s. */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $maxSpeed = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $averageHeartrate = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $maxHeartrate = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $hasHeartrate = false;

    #[ORM\Column(type: Types::INTEGER)]
    private int $kudosCount = 0;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $gearId = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $trainer = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $commute = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $manual = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $private = false;

    /** Google-encoded summary polyline of the route. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summaryPolyline = null;

    #[ORM\Column(
        type: PostGISType::GEOGRAPHY,
        nullable: true,
        options: ['geometry_type' => 'POINT', 'srid' => 4326],
    )]
    private ?string $startLatlng = null;

    #[ORM\Column(
        type: PostGISType::GEOGRAPHY,
        nullable: true,
        options: ['geometry_type' => 'POINT', 'srid' => 4326],
    )]
    private ?string $endLatlng = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $importedAt;

    public function __construct(string $id, Athlete $athlete)
    {
        $this->id = $id;
        $this->athlete = $athlete;
        $now = new \DateTimeImmutable();
        $this->startDate = $now;
        $this->startDateLocal = $now;
        $this->importedAt = $now;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAthlete(): Athlete
    {
        return $this->athlete;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getSportType(): string
    {
        return $this->sportType;
    }

    public function setSportType(string $sportType): self
    {
        $this->sportType = $sportType;

        return $this;
    }

    public function getDistance(): float
    {
        return $this->distance;
    }

    public function setDistance(float $distance): self
    {
        $this->distance = $distance;

        return $this;
    }

    public function getMovingTime(): int
    {
        return $this->movingTime;
    }

    public function setMovingTime(int $movingTime): self
    {
        $this->movingTime = $movingTime;

        return $this;
    }

    public function getElapsedTime(): int
    {
        return $this->elapsedTime;
    }

    public function setElapsedTime(int $elapsedTime): self
    {
        $this->elapsedTime = $elapsedTime;

        return $this;
    }

    public function getTotalElevationGain(): float
    {
        return $this->totalElevationGain;
    }

    public function setTotalElevationGain(float $gain): self
    {
        $this->totalElevationGain = $gain;

        return $this;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getStartDateLocal(): \DateTimeImmutable
    {
        return $this->startDateLocal;
    }

    public function setStartDateLocal(\DateTimeImmutable $startDateLocal): self
    {
        $this->startDateLocal = $startDateLocal;

        return $this;
    }

    public function setTimezone(?string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function getAverageSpeed(): ?float
    {
        return $this->averageSpeed;
    }

    public function setAverageSpeed(?float $v): self
    {
        $this->averageSpeed = $v;

        return $this;
    }

    public function getMaxSpeed(): ?float
    {
        return $this->maxSpeed;
    }

    public function setMaxSpeed(?float $v): self
    {
        $this->maxSpeed = $v;

        return $this;
    }

    public function getAverageHeartrate(): ?float
    {
        return $this->averageHeartrate;
    }

    public function setAverageHeartrate(?float $v): self
    {
        $this->averageHeartrate = $v;

        return $this;
    }

    public function getMaxHeartrate(): ?float
    {
        return $this->maxHeartrate;
    }

    public function setMaxHeartrate(?float $v): self
    {
        $this->maxHeartrate = $v;

        return $this;
    }

    public function hasHeartrate(): bool
    {
        return $this->hasHeartrate;
    }

    public function setHasHeartrate(bool $v): self
    {
        $this->hasHeartrate = $v;

        return $this;
    }

    public function getKudosCount(): int
    {
        return $this->kudosCount;
    }

    public function setKudosCount(int $v): self
    {
        $this->kudosCount = $v;

        return $this;
    }

    public function getGearId(): ?string
    {
        return $this->gearId;
    }

    public function setGearId(?string $v): self
    {
        $this->gearId = $v;

        return $this;
    }

    public function isTrainer(): bool
    {
        return $this->trainer;
    }

    public function setTrainer(bool $v): self
    {
        $this->trainer = $v;

        return $this;
    }

    public function isCommute(): bool
    {
        return $this->commute;
    }

    public function setCommute(bool $v): self
    {
        $this->commute = $v;

        return $this;
    }

    public function isManual(): bool
    {
        return $this->manual;
    }

    public function setManual(bool $v): self
    {
        $this->manual = $v;

        return $this;
    }

    public function isPrivate(): bool
    {
        return $this->private;
    }

    public function setPrivate(bool $v): self
    {
        $this->private = $v;

        return $this;
    }

    public function getSummaryPolyline(): ?string
    {
        return $this->summaryPolyline;
    }

    public function setSummaryPolyline(?string $v): self
    {
        $this->summaryPolyline = $v;

        return $this;
    }

    public function getStartLatlng(): ?string
    {
        return $this->startLatlng;
    }

    /** @param array{0: float, 1: float}|null $latlng [lat, lng] */
    public function setStartLatlng(?array $latlng): self
    {
        $this->startLatlng = null === $latlng
            ? null
            : sprintf('SRID=4326;POINT(%F %F)', $latlng[1], $latlng[0]);

        return $this;
    }

    public function getEndLatlng(): ?string
    {
        return $this->endLatlng;
    }

    /** @param array{0: float, 1: float}|null $latlng [lat, lng] */
    public function setEndLatlng(?array $latlng): self
    {
        $this->endLatlng = null === $latlng
            ? null
            : sprintf('SRID=4326;POINT(%F %F)', $latlng[1], $latlng[0]);

        return $this;
    }

    public function getImportedAt(): \DateTimeImmutable
    {
        return $this->importedAt;
    }

    public function markImported(): self
    {
        $this->importedAt = new \DateTimeImmutable();

        return $this;
    }
}
