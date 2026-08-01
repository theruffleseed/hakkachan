<?php

namespace App\Controller;

use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

class StripeWebhookController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'STRIPE_WEBHOOK_SECRET')]
        private readonly string $webhookSecret,
        #[Autowire(env: 'RESERVATION_NOTIFY_EMAIL')]
        private readonly string $notifyEmail,
        private readonly ReservationRepository $reservations,
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/stripe/webhook', name: 'app_stripe_webhook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $signature = $request->headers->get('stripe-signature');
        if (!$signature) {
            return new Response('Missing stripe-signature header', 400);
        }

        try {
            $event = Webhook::constructEvent($request->getContent(), $signature, $this->webhookSecret);
        } catch (SignatureVerificationException $e) {
            return new Response('Webhook signature verification failed: ' . $e->getMessage(), 400);
        }

        if ($event->type === Event::CHECKOUT_SESSION_COMPLETED) {
            /** @var Session $session */
            $session = $event->data->object;

            $reservation = $this->reservations->findByStripeSessionId($session->id);
            if ($reservation && $reservation->getStatus() !== 'paid') {
                $reservation->markPaid($session->customer_details?->email ?? 'unknown');
                $this->em->flush();

                if ($this->notifyEmail !== '') {
                    $this->mailer->send((new Email())
                        ->from($this->notifyEmail)
                        ->to($this->notifyEmail)
                        ->subject(\sprintf('New reservation: %s, %d pax', $reservation->getSeatingDate()->format('D, j M Y'), $reservation->getPax()))
                        ->text(\sprintf(
                            "Date: %s\nPax: %d\nAmount: RM%s\nGuest email: %s\nStripe session: %s\n",
                            $reservation->getSeatingDate()->format('D, j M Y'),
                            $reservation->getPax(),
                            number_format($reservation->getAmountCents() / 100, 2),
                            $reservation->getGuestEmail() ?? 'unknown',
                            $session->id,
                        )));
                }
            }

            $this->logger->info('Reservation paid', [
                'session_id' => $session->id,
                'amount_total' => $session->amount_total,
                'email' => $session->customer_details?->email,
            ]);
        }

        return new Response(null, 200);
    }
}
