<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Reservation\GuestDetails;
use App\Reservation\Pricing;
use App\Reservation\SeatingCalendar;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    public function __construct(
        private readonly ReservationRepository $reservations,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/admin', name: 'app_admin', methods: ['GET'])]
    public function index(): Response
    {
        $bookings = $this->reservations->findPaidFrom(new \DateTimeImmutable('today'));

        $pax = 0;
        foreach ($bookings as $booking) {
            $pax += $booking->getPax();
        }

        return $this->render('page/admin.html.twig', [
            'bookings' => $bookings,
            'totalPax' => $pax,
        ]);
    }

    #[Route('/admin/{id}', name: 'app_admin_edit', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function edit(Reservation $booking): Response
    {
        return $this->render('page/admin_edit.html.twig', [
            'booking' => $booking,
            'minPax' => Pricing::MIN_PAX,
            'capacity' => SeatingCalendar::CAPACITY_PAX,
        ]);
    }

    #[Route('/admin/{id}', name: 'app_admin_update', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function update(Request $request, Reservation $booking): Response
    {
        $this->isCsrfTokenValid('admin', $request->request->get('_csrf_token'))
            or throw $this->createAccessDeniedException('Invalid CSRF token.');

        $guest = GuestDetails::fromInput(
            $request->request->get('name'),
            $request->request->get('phone'),
            $request->request->get('email'),
        );
        $pax = (int) $request->request->get('pax');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->request->get('date')) ?: null;

        if (!$guest || !$date || $pax < Pricing::MIN_PAX) {
            $this->addFlash('error', sprintf('Need a name, a valid date, and at least %d pax.', Pricing::MIN_PAX));

            return $this->redirectToRoute('app_admin_edit', ['id' => $booking->getId()]);
        }

        // Seats already paid for that night, ignoring this booking's own — it is
        // about to be replaced by whatever the form says.
        $taken = $this->reservations->paidPaxForDate($date);
        if ($booking->getStatus() === 'paid' && $date == $booking->getSeatingDate()) {
            $taken -= $booking->getPax();
        }

        if ($taken + $pax > SeatingCalendar::CAPACITY_PAX) {
            $this->addFlash('error', sprintf('Only %d seats left on %s.', SeatingCalendar::CAPACITY_PAX - $taken, $date->format('D, j M Y')));

            return $this->redirectToRoute('app_admin_edit', ['id' => $booking->getId()]);
        }

        $booking->setGuest($guest);
        $booking->setPax($pax);
        $booking->setSeatingDate($date);
        $this->em->flush();

        $this->addFlash('notice', sprintf('Updated %s.', $guest->name));

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/admin/{id}/delete', name: 'app_admin_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Reservation $booking): Response
    {
        $this->isCsrfTokenValid('admin', $request->request->get('_csrf_token'))
            or throw $this->createAccessDeniedException('Invalid CSRF token.');

        $name = $booking->getGuestName() ?? 'booking';
        $this->em->remove($booking);
        $this->em->flush();

        $this->addFlash('notice', sprintf('Deleted %s. Any payment must be refunded in Stripe.', $name));

        return $this->redirectToRoute('app_admin');
    }
}
