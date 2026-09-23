<?php

namespace App\Reservation;

use App\Entity\Reservation;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * One place to mail about a paid booking, whatever the payment route —
 * Stripe webhook or an admin entering a cash/transfer payment by hand.
 *
 * notify() alerts the restaurant; notifyGuest() confirms to the guest.
 * Both are skipped when RESERVATION_NOTIFY_EMAIL is unset (no sender).
 * The guest mail is additionally skipped when the booking has no address —
 * only possible for rows created before email became mandatory.
 */
final readonly class ReservationNotifier
{
    public function __construct(
        #[Autowire(env: 'RESERVATION_NOTIFY_EMAIL')]
        private string $notifyEmail,
        private MailerInterface $mailer,
    ) {
    }

    public function notify(Reservation $reservation, string $source): void
    {
        if ($this->notifyEmail === '') {
            return;
        }

        $this->mailer->send((new Email())
            ->from($this->notifyEmail)
            ->to($this->notifyEmail)
            ->subject(\sprintf(
                'New reservation: %s — %s, %d pax',
                $reservation->getGuestName() ?? 'unknown',
                $reservation->getSeatingDate()->format('D, j M Y'),
                $reservation->getPax(),
            ))
            ->text(\sprintf(
                "Name: %s\nPhone: %s\nEmail: %s\nGuests: %d\n\nDate: %s\nAmount: RM%s\nPayment: %s\n",
                $reservation->getGuestName() ?? 'unknown',
                $reservation->getGuestPhone() ?? 'unknown',
                $reservation->getGuestEmail() ?? 'unknown',
                $reservation->getPax(),
                $reservation->getSeatingDate()->format('D, j M Y'),
                number_format($reservation->getAmountCents() / 100, 2),
                $source,
            )));
    }

    public function notifyGuest(Reservation $reservation, string $source): void
    {
        $guestEmail = $reservation->getGuestEmail();
        if ('' === $this->notifyEmail || null === $guestEmail || '' === $guestEmail) {
            return;
        }

        $this->mailer->send((new Email())
            ->from($this->notifyEmail)
            ->to($guestEmail)
            ->subject(\sprintf(
                'Your Hakkachan reservation — %s, %d pax',
                $reservation->getSeatingDate()->format('D, j M Y'),
                $reservation->getPax(),
            ))
            ->text(\sprintf(
                "Hi %s,\n\nYour seats are reserved — we've received your payment.\n\nDate: %s\nGuests: %d\nAmount: RM%s\nPayment: %s\n\nPlease arrive between 6:00pm and 8:00pm. Reply to this email if you need to change anything.\n\n— Hakkachan\n",
                $reservation->getGuestName() ?? 'guest',
                $reservation->getSeatingDate()->format('D, j M Y'),
                $reservation->getPax(),
                number_format($reservation->getAmountCents() / 100, 2),
                $source,
            )));
    }
}
