<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AdminArea;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminArea>
 */
class AdminAreaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminArea::class);
    }

    public function save(AdminArea $area, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($area);
        if ($flush) {
            $em->flush();
        }
    }

    /**
     * @return list<AdminArea>
     */
    public function findByLevel(int $level): array
    {
        return array_values($this->findBy(['adminLevel' => $level], ['name' => 'ASC']));
    }
}
