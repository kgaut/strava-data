<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AdminAreaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Jsor\Doctrine\PostGIS\Types\PostGISType;

/**
 * OSM administrative boundary (department or commune). Stored as a single
 * MULTIPOLYGON geography in SRID 4326.
 */
#[ORM\Entity(repositoryClass: AdminAreaRepository::class)]
#[ORM\Table(name: 'admin_area')]
#[ORM\Index(name: 'admin_area_level_idx', columns: ['admin_level'])]
class AdminArea
{
    public const LEVEL_DEPARTMENT = 6;
    public const LEVEL_COMMUNE = 8;

    #[ORM\Id]
    #[ORM\Column(type: Types::BIGINT)]
    private string $id;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $adminLevel;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(
        type: PostGISType::GEOGRAPHY,
        options: ['geometry_type' => 'MULTIPOLYGON', 'srid' => 4326],
    )]
    private string $geometry;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $importedAt;

    public function __construct(string $id, int $adminLevel, string $name, string $geometry)
    {
        $this->id = $id;
        $this->adminLevel = $adminLevel;
        $this->name = $name;
        $this->geometry = $geometry;
        $this->importedAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAdminLevel(): int
    {
        return $this->adminLevel;
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;

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

    public function touchImported(): self
    {
        $this->importedAt = new \DateTimeImmutable();

        return $this;
    }
}
