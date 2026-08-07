<?php

namespace App\Reservation;

use App\Entity\Reservation;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * One place to notify the restaurant of a paid booking, whatever the payment
 * route — Stripe webhook or an admin entering a cash/transfer payment by hand.
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
}
