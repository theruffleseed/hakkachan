<?php

namespace App\Repository;

use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function paidPaxForDate(\DateTimeImmutable $date): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COALESCE(SUM(r.pax), 0)')
            ->andWhere('r.seatingDate = :date')
            ->andWhere('r.status = :status')
            ->setParameter('date', $date)
            ->setParameter('status', 'paid')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByStripeSessionId(string $stripeSessionId): ?Reservation
    {
        return $this->findOneBy(['stripeSessionId' => $stripeSessionId]);
    }
}
