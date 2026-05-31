<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RoadSegmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Jsor\Doctrine\PostGIS\Types\PostGISType;

/**
 * Single OSM highway way (road / path / cycleway / track / footway / …).
 */
#[ORM\Entity(repositoryClass: RoadSegmentRepository::class)]
#[ORM\Table(name: 'road_segment')]
#[ORM\Index(name: 'road_segment_highway_idx', columns: ['highway'])]
#[ORM\Index(name: 'road_segment_commune_idx', columns: ['commune_id'])]
#[ORM\Index(name: 'road_segment_department_idx', columns: ['department_id'])]
class RoadSegment
{
    #[ORM\Id]
    #[ORM\Column(type: Types::BIGINT)]
    private string $id;

    #[ORM\Column(length: 50)]
    private string $highway;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $surface = null;

    #[ORM\Column(
        type: PostGISType::GEOGRAPHY,
        options: ['geometry_type' => 'LINESTRING', 'srid' => 4326],
    )]
    private string $geometry;

    #[ORM\Column(type: Types::FLOAT)]
    private float $lengthM;

    #[ORM\ManyToOne(targetEntity: AdminArea::class)]
    #[ORM\JoinColumn(name: 'commune_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AdminArea $commune = null;

    #[ORM\ManyToOne(targetEntity: AdminArea::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AdminArea $department = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $importedAt;

    public function __construct(string $id, string $highway, string $geometry, float $lengthM)
    {
        $this->id = $id;
        $this->highway = $highway;
        $this->geometry = $geometry;
        $this->lengthM = $lengthM;
        $this->importedAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getHighway(): string
    {
        return $this->highway;
    }

    public function setHighway(string $highway): self
    {
        $this->highway = $highway;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSurface(): ?string
    {
        return $this->surface;
    }

    public function setSurface(?string $surface): self
    {
        $this->surface = $surface;

        return $this;
    }

    public function getGeometry(): string
    {
        return $this->geometry;
    }

    public function setGeometry(string $geometry): self
    {
        $this->geometry = $geometry;

        return $this;
    }

    public function getLengthM(): float
    {
        return $this->lengthM;
    }

    public function setLengthM(float $lengthM): self
    {
        $this->lengthM = $lengthM;

        return $this;
    }

    public function getCommune(): ?AdminArea
    {
        return $this->commune;
    }

    public function setCommune(?AdminArea $commune): self
    {
        $this->commune = $commune;

        return $this;
    }

    public function getDepartment(): ?AdminArea
    {
        return $this->department;
    }

    public function setDepartment(?AdminArea $department): self
    {
        $this->department = $department;

        return $this;
    }

    public function touchImported(): self
    {
        $this->importedAt = new \DateTimeImmutable();

        return $this;
    }
}
