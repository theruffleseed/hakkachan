<?php

namespace App\Tests\Reservation;

use App\Entity\ClosedDate;
use App\Entity\Reservation;
use App\Reservation\SeatingCalendar;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Admin time-off: closing a seating date blocks checkout and shows the date
 * as Closed on /reserve. Closing warns but still lands when paid bookings
 * exist — guests are moved/refunded by hand from /admin.
 */
class AdminClosedTest extends WebTestCase
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

    public function testAdminCanCloseAndReopenADate(): void
    {
        $friday = $this->bookableFriday()->format('Y-m-d');

        $this->client->request('POST', '/admin/closed', [
            '_csrf_token' => $this->adminCsrfToken(),
            'date' => $friday,
        ]);
        self::assertResponseRedirects('/admin/closed');
        self::assertNotNull($this->findClosed($friday));

        $this->client->request('POST', "/admin/closed/{$friday}/delete", [
            '_csrf_token' => $this->adminCsrfToken(),
            'date' => $friday,
        ]);
        self::assertResponseRedirects('/admin/closed');
        self::assertNull($this->findClosed($friday));
    }

    public function testClosingAWeekdayIsRejected(): void
    {
        $wednesday = (new \DateTimeImmutable('next wednesday'))->format('Y-m-d');

        $this->client->request('POST', '/admin/closed', [
            '_csrf_token' => $this->adminCsrfToken(),
            'date' => $wednesday,
        ]);
        self::assertResponseRedirects('/admin/closed');
        self::assertNull($this->findClosed($wednesday));
    }

    public function testClosingTwiceKeepsOneRow(): void
    {
        $friday = $this->bookableFriday()->format('Y-m-d');

        foreach ([1, 2] as $_) {
            $this->client->request('POST', '/admin/closed', [
                '_csrf_token' => $this->adminCsrfToken(),
                'date' => $friday,
            ]);
            self::assertResponseRedirects('/admin/closed');
        }

        self::assertCount(1, $this->em->getRepository(ClosedDate::class)->findBy([
            'seatingDate' => new \DateTimeImmutable($friday),
        ]));
    }

    public function testCheckoutRejectsAClosedDate(): void
    {
        $friday = $this->bookableFriday();
        $this->em->persist(new ClosedDate($friday));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/reserve');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('input[name="_csrf_token"]')->first()->attr('value');

        $this->client->request('POST', '/reserve/checkout', [
            '_csrf_token' => $token,
            'name' => 'Closed Guest',
            'phone' => '',
            'email' => '',
            'date' => $friday->format('Y-m-d'),
            'pax' => 2,
        ]);

        self::assertResponseRedirects('/reserve');
        self::assertNull($this->em->getRepository(Reservation::class)->findOneBy([
            'guestName' => 'Closed Guest',
        ]));
    }

    public function testClosedDateShowsAsClosedOnReserve(): void
    {
        $friday = $this->bookableFriday();
        $this->em->persist(new ClosedDate($friday));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/reserve');
        self::assertResponseIsSuccessful();

        $option = $crawler->filter(sprintf('select[name="date"] option[value="%s"]', $friday->format('Y-m-d')));
        self::assertSame(1, $option->count());
        self::assertStringContainsString('(Closed)', $option->text());
        self::assertNotNull($option->attr('disabled'));
    }

    private function findClosed(string $ymd): ?ClosedDate
    {
        return $this->em->getRepository(ClosedDate::class)->findOneBy([
            'seatingDate' => new \DateTimeImmutable($ymd),
        ]);
    }

    private function bookableFriday(): \DateTimeImmutable
    {
        $today = new \DateTimeImmutable('today');
        foreach (SeatingCalendar::upcomingDates($today) as $date) {
            if (5 === (int) $date->format('N') && SeatingCalendar::isOpenForBooking($date, $today)) {
                return $date;
            }
        }

        self::fail('No bookable Friday inside the booking window.');
    }

    private function adminCsrfToken(): string
    {
        $crawler = $this->client->request('GET', '/admin/closed');
        self::assertResponseIsSuccessful();

        return $crawler->filter('input[name="_csrf_token"]')->first()->attr('value');
    }
}
