<?php

namespace App\Repository;

use App\Entity\ClosedDate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClosedDate>
 */
class ClosedDateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClosedDate::class);
    }

    public function isClosed(\DateTimeImmutable $date): bool
    {
        return null !== $this->createQueryBuilder('c')
            ->select('c.id')
            ->andWhere('c.seatingDate = :date')
            ->setParameter('date', $date, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param \DateTimeImmutable[] $dates
     *
     * @return array<string,true> keyed by Y-m-d
     */
    public function closedSetFor(array $dates): array
    {
        if ([] === $dates) {
            return [];
        }

        $min = min($dates);
        $future = $this->findFutureFrom($min);
        $wanted = [];
        foreach ($dates as $d) {
            $wanted[$d->format('Y-m-d')] = true;
        }

        $set = [];
        foreach ($future as $closed) {
            $key = $closed->getSeatingDate()->format('Y-m-d');
            if (isset($wanted[$key])) {
                $set[$key] = true;
            }
        }

        return $set;
    }

    /**
     * All future closures (incl. today), soonest first.
     *
     * @return ClosedDate[]
     */
    public function findFutureFrom(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.seatingDate >= :date')
            ->setParameter('date', $date, Types::DATE_IMMUTABLE)
            ->orderBy('c.seatingDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
