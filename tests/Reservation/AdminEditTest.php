<?php

namespace App\Tests\Reservation;

use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Editing a booking re-checks capacity, and has to ignore the booking's own
 * seats — otherwise a booking can never be edited up to a full room, and the
 * night it is moved off looks fuller than it is.
 */
class AdminEditTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = self::createClient(server: [
            'PHP_AUTH_USER' => 'stevie',
            'PHP_AUTH_PW' => 'intasek1967',
        ]);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($this->em);
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
    }

    public function testOwnSeatsDoNotCountAgainstTheBookingBeingEdited(): void
    {
        $id = $this->paidBooking('2026-08-07', 16);

        $this->submitEdit($id, '2026-08-07', 18);

        self::assertResponseRedirects('/admin');
        self::assertSame(18, $this->em->find(Reservation::class, $id)->getPax());
    }

    public function testEditBeyondCapacityIsRejected(): void
    {
        $this->paidBooking('2026-08-07', 14);
        $id = $this->paidBooking('2026-08-07', 4);

        $this->submitEdit($id, '2026-08-07', 5);

        self::assertResponseRedirects('/admin/' . $id);
        self::assertSame(4, $this->em->find(Reservation::class, $id)->getPax());
    }

    public function testDeleteFreesTheSeats(): void
    {
        $id = $this->paidBooking('2026-08-07', 6);

        $this->client->request('POST', '/admin/' . $id . '/delete', [
            '_csrf_token' => $this->csrfToken($id),
        ]);

        self::assertResponseRedirects('/admin');
        self::assertNull($this->em->find(Reservation::class, $id));
    }

    private function paidBooking(string $date, int $pax): int
    {
        $booking = new Reservation(new \DateTimeImmutable($date), $pax, $pax * 19800, 'Guest', null, null);
        $booking->markPaid();
        $this->em->persist($booking);
        $this->em->flush();

        return $booking->getId();
    }

    private function submitEdit(int $id, string $date, int $pax): void
    {
        $token = $this->csrfToken($id);
        $this->em->clear();

        $this->client->request('POST', '/admin/' . $id, [
            '_csrf_token' => $token,
            'name' => 'Guest',
            'phone' => '',
            'email' => '',
            'date' => $date,
            'pax' => $pax,
        ]);
    }

    private function csrfToken(int $id): string
    {
        $crawler = $this->client->request('GET', '/admin/' . $id);

        return $crawler->filter('input[name="_csrf_token"]')->first()->attr('value');
    }
}
