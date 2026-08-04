<?php

namespace App\Tests\Reservation;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Counting paid pax crosses the ORM, so it can only break in a real database:
 * a date bound with the wrong Doctrine type silently matches nothing and every
 * seating looks empty.
 */
class PaidPaxForDateTest extends KernelTestCase
{
    public function testPaidReservationsCountAgainstTheirDate(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $schema = new SchemaTool($em);
        $schema->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $schema->createSchema($em->getMetadataFactory()->getAllMetadata());

        $date = new \DateTimeImmutable('2026-08-07');
        $paid = new Reservation($date, 4, 40000, 'Paid Guest', null, null);
        $paid->markPaid();
        $em->persist($paid);
        $em->persist(new Reservation($date, 2, 20000, 'Pending Guest', null, null));
        $em->persist(new Reservation(new \DateTimeImmutable('2026-08-08'), 6, 60000, 'Other Night', null, null));
        $em->flush();

        $repository = self::getContainer()->get(ReservationRepository::class);

        self::assertSame(4, $repository->paidPaxForDate($date));
    }
}
