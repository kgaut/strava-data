<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\RoadVisit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RoadVisit>
 */
class RoadVisitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoadVisit::class);
    }

    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.roadSegment)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Length (in meters) of segments first visited by the given activity.
     */
    public function newKilometersForActivity(Activity $activity): float
    {
        $sql = <<<'SQL'
            SELECT COALESCE(SUM(r.length_m), 0) AS m
            FROM road_visit v
            JOIN road_segment r ON r.id = v.road_segment_id
            WHERE v.first_activity_id = :activity_id
        SQL;

        $row = $this->getEntityManager()->getConnection()->fetchAssociative($sql, [
            'activity_id' => $activity->getId(),
        ]);

        return (float) ($row['m'] ?? 0);
    }
}
