<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Reservation\GuestDetails;
use App\Reservation\Pricing;
use App\Reservation\SeatingCalendar;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session;
use Stripe\StripeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ReservationController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'STRIPE_SECRET_KEY')]
        private readonly string $stripeSecretKey,
        private readonly ReservationRepository $reservations,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/reserve', name: 'app_reserve', methods: ['GET'])]
    public function index(): Response
    {
        $today = new \DateTimeImmutable('today');
        $dates = [];
        foreach (SeatingCalendar::upcomingDates($today) as $date) {
            $remaining = SeatingCalendar::CAPACITY_PAX - $this->reservations->paidPaxForDate($date);
            // A date with fewer seats left than the minimum booking size can't be booked at all.
            if ($remaining >= Pricing::MIN_PAX) {
                $dates[] = ['date' => $date, 'remaining' => $remaining];
            }
        }

        return $this->render('page/reserve.html.twig', [
            'pricePerPax' => Pricing::PRICE_PER_PAX_CENTS / 100,
            'minPax' => Pricing::MIN_PAX,
            'bookingWeeks' => SeatingCalendar::BOOKING_WEEKS,
            'dates' => $dates,
        ]);
    }

    #[Route('/reserve/checkout', name: 'app_reserve_checkout', methods: ['POST'])]
    public function checkout(Request $request): Response
    {
        $this->isCsrfTokenValid('submit', $request->request->get('_csrf_token'))
            or throw $this->createAccessDeniedException('Invalid CSRF token.');

        $pax = (int) $request->request->get('pax');
        $dateInput = (string) $request->request->get('date');
        $guest = GuestDetails::fromInput(
            $request->request->get('name'),
            $request->request->get('phone'),
            $request->request->get('email'),
        );

        if (!$guest) {
            $this->addFlash('error', 'Please give us your name, and a valid email address if you enter one.');

            return $this->redirectToRoute('app_reserve');
        }

        $seatingDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateInput) ?: null;
        $isValidSeatingDate = $seatingDate && \in_array($seatingDate, SeatingCalendar::upcomingDates(new \DateTimeImmutable('today')), false);

        if (!$isValidSeatingDate || $pax < Pricing::MIN_PAX) {
            $this->addFlash('error', 'Please pick a valid date and pax count.');

            return $this->redirectToRoute('app_reserve');
        }

        // ponytail: check-then-insert, no row lock — fine at 18-seat/low-concurrency
        // scale; add a transaction + pessimistic lock if this ever sees real contention.
        $remaining = SeatingCalendar::CAPACITY_PAX - $this->reservations->paidPaxForDate($seatingDate);
        if ($pax > $remaining) {
            $this->addFlash('error', 'Not enough seats left for that date.');

            return $this->redirectToRoute('app_reserve');
        }

        $reservation = new Reservation($seatingDate, $pax, Pricing::amountCents($pax), $guest->name, $guest->phone, $guest->email);
        $this->em->persist($reservation);
        $this->em->flush();

        $stripe = new StripeClient($this->stripeSecretKey);

        /** @var Session $session */
        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => 'myr',
                    'product_data' => ['name' => \sprintf('Hakkachan Set Dinner — %s', $seatingDate->format('D, j M Y'))],
                    'unit_amount' => Pricing::PRICE_PER_PAX_CENTS,
                ],
                'quantity' => $pax,
            ]],
            // Name and phone come from our own form, so Stripe only needs the card.
            // Without an email of our own, Stripe asks for one to send the receipt.
            ...($guest->email ? ['customer_email' => $guest->email] : []),
            'metadata' => ['reservation_id' => $reservation->getId()],
            'success_url' => $this->generateUrl('app_reserve_success', [], UrlGeneratorInterface::ABSOLUTE_URL) . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->generateUrl('app_reserve_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        $reservation->setStripeSessionId($session->id);
        $this->em->flush();

        return $this->redirect($session->url);
    }

    #[Route('/reserve/success', name: 'app_reserve_success', methods: ['GET'])]
    public function success(): Response
    {
        return $this->render('page/reserve_success.html.twig');
    }

    #[Route('/reserve/cancel', name: 'app_reserve_cancel', methods: ['GET'])]
    public function cancel(): Response
    {
        return $this->render('page/reserve_cancel.html.twig');
    }
}
