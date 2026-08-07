<?php

namespace App\Tests\Reservation;

use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Admin-entered bookings (cash/direct transfer) skip Stripe but must still
 * behave like a paid booking: counted against capacity and notified by email.
 */
class AdminCreateTest extends WebTestCase
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

    public function testAdminCanAddAPaidBookingAndGetsNotified(): void
    {
        $token = $this->csrfToken();

        $this->client->request('POST', '/admin/new', [
            '_csrf_token' => $token,
            'name' => 'Cash Guest',
            'phone' => '0123456789',
            'email' => '',
            'date' => '2026-08-07',
            'pax' => 4,
        ]);

        self::assertResponseRedirects('/admin');
        self::assertQueuedEmailCount(1);

        $booking = $this->em->getRepository(Reservation::class)->findOneBy(['guestName' => 'Cash Guest']);
        self::assertNotNull($booking);
        self::assertSame('paid', $booking->getStatus());
        self::assertSame(4, $booking->getPax());
    }

    public function testAddingBeyondCapacityIsRejected(): void
    {
        $existing = new Reservation(new \DateTimeImmutable('2026-08-07'), 16, 16 * 19800, 'Guest', null, null);
        $existing->markPaid();
        $this->em->persist($existing);
        $this->em->flush();

        $token = $this->csrfToken();

        $this->client->request('POST', '/admin/new', [
            '_csrf_token' => $token,
            'name' => 'Overflow Guest',
            'phone' => '',
            'email' => '',
            'date' => '2026-08-07',
            'pax' => 4,
        ]);

        self::assertResponseRedirects('/admin/new');
        self::assertQueuedEmailCount(0);
        self::assertNull($this->em->getRepository(Reservation::class)->findOneBy(['guestName' => 'Overflow Guest']));
    }

    private function csrfToken(): string
    {
        $crawler = $this->client->request('GET', '/admin/new');

        return $crawler->filter('input[name="_csrf_token"]')->first()->attr('value');
    }
}
