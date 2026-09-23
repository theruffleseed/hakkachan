<?php

namespace App\Tests\Reservation;

use App\Entity\Reservation;
use App\Reservation\ReservationNotifier;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * A paid booking mails twice: one alert to the restaurant, one confirmation
 * to the guest. Legacy rows without an address only mail the restaurant.
 */
final class ReservationNotifierTest extends WebTestCase
{
    public function testPaidBookingMailsRestaurantAndGuest(): void
    {
        self::createClient();
        $notifier = self::getContainer()->get(ReservationNotifier::class);

        $booking = new Reservation(
            new \DateTimeImmutable('2026-08-07'),
            2,
            2 * 19800,
            'Wei Ling',
            '0123456789',
            'wei@example.com',
        );

        $notifier->notify($booking, 'Stripe — cs_test_123');
        $notifier->notifyGuest($booking, 'Stripe — cs_test_123');

        self::assertEmailCount(2);
    }

    public function testGuestMailSkippedWithoutGuestAddress(): void
    {
        self::createClient();
        $notifier = self::getContainer()->get(ReservationNotifier::class);

        $booking = new Reservation(
            new \DateTimeImmutable('2026-08-07'),
            2,
            2 * 19800,
            'Walk-in Guest',
            null,
            null,
        );

        $notifier->notify($booking, 'Admin — cash/transfer');
        $notifier->notifyGuest($booking, 'Admin — cash/transfer');

        self::assertEmailCount(1);
    }

    public function testCommaSeparatedNotifyEmailsMailEveryInbox(): void
    {
        self::createClient();
        $notifier = new ReservationNotifier(
            'admin@example.com,alerts@example.com',
            self::getContainer()->get(MailerInterface::class),
        );

        $booking = new Reservation(
            new \DateTimeImmutable('2026-08-07'),
            2,
            2 * 19800,
            'Wei Ling',
            '0123456789',
            'wei@example.com',
        );

        $notifier->notify($booking, 'Admin — cash/transfer');

        self::assertEmailCount(1);
        $to = array_map(static fn (Address $a): string => $a->getAddress(), self::getMailerMessage(0)->getTo());
        self::assertSame(['admin@example.com', 'alerts@example.com'], $to);
    }
}
