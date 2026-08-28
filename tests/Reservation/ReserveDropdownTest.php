<?php

namespace App\Tests\Reservation;

use App\Entity\Reservation;
use App\Reservation\SeatingCalendar;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ReserveDropdownTest extends WebTestCase
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

    public function testSeatCountOnlyShowsBelowFourAndFullDatesStayVisibleDisabled(): void
    {
        $today = new \DateTimeImmutable('today');
        $dates = SeatingCalendar::upcomingDates($today);
        $open = array_values(array_filter(
            $dates,
            static fn (\DateTimeImmutable $d): bool => SeatingCalendar::isOpenForBooking($d, $today),
        ));
        self::assertGreaterThanOrEqual(4, \count($open));

        $this->seedPaid($open[0], SeatingCalendar::CAPACITY_PAX); // 0 left
        $this->seedPaid($open[1], SeatingCalendar::CAPACITY_PAX - 3); // 3 left
        $this->seedPaid($open[2], SeatingCalendar::CAPACITY_PAX - 4); // 4 left — hide count
        $this->seedPaid($open[3], SeatingCalendar::CAPACITY_PAX - 1); // 1 left — unbookable

        $crawler = $this->client->request('GET', '/reserve');
        self::assertResponseIsSuccessful();

        $options = $crawler->filter('select[name="date"] option');
        self::assertSame(\count($dates), $options->count());

        foreach ($dates as $date) {
            if (SeatingCalendar::isOpenForBooking($date, $today)) {
                continue;
            }
            $option = $crawler->filter(sprintf('select[name="date"] option[value="%s"]', $date->format('Y-m-d')));
            self::assertSame(1, $option->count());
            self::assertNotNull($option->attr('disabled'));
        }

        $full = $crawler->filter(sprintf('select[name="date"] option[value="%s"]', $open[0]->format('Y-m-d')));
        self::assertStringContainsString('(Fully Booked)', $full->text());
        self::assertStringNotContainsString('seat', $full->text());
        self::assertNotNull($full->attr('disabled'));

        $threeLeft = $crawler->filter(sprintf('select[name="date"] option[value="%s"]', $open[1]->format('Y-m-d')));
        self::assertStringContainsString('3 seats left', $threeLeft->text());
        self::assertNull($threeLeft->attr('disabled'));
        self::assertNotNull($threeLeft->attr('selected'));

        $fourLeft = $crawler->filter(sprintf('select[name="date"] option[value="%s"]', $open[2]->format('Y-m-d')));
        self::assertStringNotContainsString('seat', $fourLeft->text());
        self::assertStringNotContainsString('Fully Booked', $fourLeft->text());
        self::assertNull($fourLeft->attr('disabled'));

        $oneLeft = $crawler->filter(sprintf('select[name="date"] option[value="%s"]', $open[3]->format('Y-m-d')));
        self::assertStringContainsString('1 seat left', $oneLeft->text());
        self::assertNotNull($oneLeft->attr('disabled'));
    }

    public function testGuestCountMatchesSeatsLeft(): void
    {
        $today = new \DateTimeImmutable('today');
        $open = array_values(array_filter(
            SeatingCalendar::upcomingDates($today),
            static fn (\DateTimeImmutable $d): bool => SeatingCalendar::isOpenForBooking($d, $today),
        ));
        $this->seedPaid($open[0], SeatingCalendar::CAPACITY_PAX - 5);

        $crawler = $this->client->request('GET', '/reserve');
        self::assertResponseIsSuccessful();

        $values = $crawler->filter('select[name="pax"] option')->each(
            static fn ($node) => $node->attr('value'),
        );
        self::assertSame(['2', '3', '4', '5'], $values);
    }

    private function seedPaid(\DateTimeImmutable $date, int $pax): void
    {
        $booking = new Reservation($date, $pax, $pax * 19800, 'Guest '.$date->format('Y-m-d'), null, null);
        $booking->markPaid();
        $this->em->persist($booking);
        $this->em->flush();
    }
}
