<?php

namespace App\Tests\Reservation;

use App\Entity\Reservation;
use App\Reservation\SeatingCalendar;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Email is mandatory on the reservation form: without it there is nowhere
 * to send the payment confirmation, so checkout must refuse before Stripe.
 */
final class CheckoutRequiresEmailTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($this->em);
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
    }

    public function testCheckoutWithoutEmailIsRejected(): void
    {
        $date = $this->bookableDate()->format('Y-m-d');
        $token = $this->csrfToken();

        $this->client->request('POST', '/reserve/checkout', [
            '_csrf_token' => $token,
            'name' => 'No Email Guest',
            'phone' => '0123456789',
            'email' => '',
            'date' => $date,
            'pax' => 2,
        ]);

        self::assertResponseRedirects('/reserve');
        self::assertEmailCount(0);
        self::assertNull($this->em->getRepository(Reservation::class)->findOneBy(['guestName' => 'No Email Guest']));
    }

    public function testCheckoutWithMalformedEmailIsRejected(): void
    {
        $date = $this->bookableDate()->format('Y-m-d');
        $token = $this->csrfToken();

        $this->client->request('POST', '/reserve/checkout', [
            '_csrf_token' => $token,
            'name' => 'Bad Email Guest',
            'phone' => '',
            'email' => 'not-an-email',
            'date' => $date,
            'pax' => 2,
        ]);

        self::assertResponseRedirects('/reserve');
        self::assertEmailCount(0);
        self::assertNull($this->em->getRepository(Reservation::class)->findOneBy(['guestName' => 'Bad Email Guest']));
    }

    private function bookableDate(): \DateTimeImmutable
    {
        $today = new \DateTimeImmutable('today');
        foreach (SeatingCalendar::upcomingDates($today) as $date) {
            if (SeatingCalendar::isOpenForBooking($date, $today)) {
                return $date;
            }
        }

        self::fail('No bookable seating date in the window.');
    }

    private function csrfToken(): string
    {
        $crawler = $this->client->request('GET', '/reserve');

        return $crawler->filter('input[name="_csrf_token"]')->first()->attr('value');
    }
}
