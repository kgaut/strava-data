<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RoadVisitRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Marks a road segment as visited, by which activity it was first reached,
 * and when. A segment has at most one row — once seen, always seen.
 */
#[ORM\Entity(repositoryClass: RoadVisitRepository::class)]
#[ORM\Table(name: 'road_visit')]
#[ORM\Index(name: 'road_visit_first_activity_idx', columns: ['first_activity_id'])]
class RoadVisit
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: RoadSegment::class)]
    #[ORM\JoinColumn(name: 'road_segment_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private RoadSegment $roadSegment;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'first_activity_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Activity $firstActivity;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $firstVisitedAt;

    public function __construct(RoadSegment $segment, ?Activity $firstActivity, \DateTimeImmutable $firstVisitedAt)
    {
        $this->roadSegment = $segment;
        $this->firstActivity = $firstActivity;
        $this->firstVisitedAt = $firstVisitedAt;
    }

    public function getRoadSegment(): RoadSegment
    {
        return $this->roadSegment;
    }

    public function getFirstActivity(): ?Activity
    {
        return $this->firstActivity;
    }

    public function setFirstActivity(?Activity $a): self
    {
        $this->firstActivity = $a;

        return $this;
    }

    public function getFirstVisitedAt(): \DateTimeImmutable
    {
        return $this->firstVisitedAt;
    }

    public function setFirstVisitedAt(\DateTimeImmutable $at): self
    {
        $this->firstVisitedAt = $at;

        return $this;
    }
}
