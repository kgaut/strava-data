<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Athlete;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Athlete>
 */
class AthleteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Athlete::class);
    }

    public function findOneByStravaId(string $stravaId): ?Athlete
    {
        return $this->find($stravaId);
    }

    public function save(Athlete $athlete, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->persist($athlete);
        if ($flush) {
            $em->flush();
        }
    }

    public function findFirst(): ?Athlete
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
