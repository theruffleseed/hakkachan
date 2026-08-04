<?php

namespace App\Repository;

use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
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
            // Without the explicit type Doctrine infers datetime_immutable and binds
            // "2026-08-07 00:00:00", which never matches a DATE column's "2026-08-07".
            ->setParameter('date', $date, Types::DATE_IMMUTABLE)
            ->setParameter('status', 'paid')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Paid bookings for the admin list, soonest seating first.
     *
     * @return Reservation[]
     */
    public function findPaidFrom(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.seatingDate >= :date')
            ->andWhere('r.status = :status')
            ->setParameter('date', $date, Types::DATE_IMMUTABLE)
            ->setParameter('status', 'paid')
            ->orderBy('r.seatingDate', 'ASC')
            ->addOrderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByStripeSessionId(string $stripeSessionId): ?Reservation
    {
        return $this->findOneBy(['stripeSessionId' => $stripeSessionId]);
    }
}
