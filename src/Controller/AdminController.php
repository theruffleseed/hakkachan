<?php

namespace App\Controller;

use App\Entity\ClosedDate;
use App\Entity\MenuItem;
use App\Entity\Reservation;
use App\Repository\ClosedDateRepository;
use App\Repository\MenuItemRepository;
use App\Reservation\GuestDetails;
use App\Reservation\Pricing;
use App\Reservation\ReservationNotifier;
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
        private readonly ClosedDateRepository $closedDates,
        private readonly MenuItemRepository $menu,
        private readonly EntityManagerInterface $em,
        private readonly ReservationNotifier $notifier,
    ) {
    }

    #[Route('/admin/new', name: 'app_admin_new', methods: ['GET'])]
    public function new(): Response
    {
        return $this->render('page/admin_new.html.twig', [
            'minPax' => Pricing::MIN_PAX,
            'capacity' => SeatingCalendar::CAPACITY_PAX,
        ]);
    }

    #[Route('/admin/new', name: 'app_admin_create', methods: ['POST'])]
    public function create(Request $request): Response
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

            return $this->redirectToRoute('app_admin_new');
        }

        $taken = $this->reservations->paidPaxForDate($date);
        if ($taken + $pax > SeatingCalendar::CAPACITY_PAX) {
            $this->addFlash('error', sprintf('Only %d seats left on %s.', SeatingCalendar::CAPACITY_PAX - $taken, $date->format('D, j M Y')));

            return $this->redirectToRoute('app_admin_new');
        }

        $booking = new Reservation($date, $pax, Pricing::amountCents($pax), $guest->name, $guest->phone, $guest->email);
        $booking->markPaid();
        $this->em->persist($booking);
        $this->em->flush();

        $this->notifier->notify($booking, 'Admin — cash/transfer');

        $this->addFlash('notice', sprintf('Added %s.', $guest->name));

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/admin', name: 'app_admin', methods: ['GET'])]
    public function index(): Response
    {
        // Already ordered by seating date, so one pass groups it.
        $bookings = $this->reservations->findPaidFrom(new \DateTimeImmutable('today'));

        $seatings = [];
        $totalPax = 0;
        foreach ($bookings as $booking) {
            $key = $booking->getSeatingDate()->format('Y-m-d');
            $seatings[$key]['date'] ??= $booking->getSeatingDate();
            $seatings[$key]['bookings'][] = $booking;
            $seatings[$key]['pax'] = ($seatings[$key]['pax'] ?? 0) + $booking->getPax();
            $totalPax += $booking->getPax();
        }

        return $this->render('page/admin.html.twig', [
            'seatings' => $seatings,
            'totalBookings' => \count($bookings),
            'totalPax' => $totalPax,
            'capacity' => SeatingCalendar::CAPACITY_PAX,
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

    #[Route('/admin/closed', name: 'app_admin_closed', methods: ['GET'])]
    public function closed(): Response
    {
        $today = new \DateTimeImmutable('today');
        $upcoming = SeatingCalendar::upcomingDates($today);
        $closedSet = $this->closedDates->closedSetFor($upcoming);

        $dates = [];
        foreach ($upcoming as $date) {
            $key = $date->format('Y-m-d');
            $dates[] = [
                'date' => $date,
                'closed' => isset($closedSet[$key]),
                'paidPax' => $this->reservations->paidPaxForDate($date),
            ];
        }

        return $this->render('page/admin_closed.html.twig', [
            'dates' => $dates,
            'futureClosed' => $this->closedDates->findFutureFrom($today),
            'capacity' => SeatingCalendar::CAPACITY_PAX,
        ]);
    }

    #[Route('/admin/closed', name: 'app_admin_closed_add', methods: ['POST'])]
    public function closeDate(Request $request): Response
    {
        $this->isCsrfTokenValid('admin', $request->request->get('_csrf_token'))
            or throw $this->createAccessDeniedException('Invalid CSRF token.');

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->request->get('date')) ?: null;
        $today = new \DateTimeImmutable('today');

        if (!$date || !\in_array((int) $date->format('N'), [5, 6], true) || $date < $today) {
            $this->addFlash('error', 'Pick a future Friday or Saturday to close.');

            return $this->redirectToRoute('app_admin_closed');
        }

        if ($this->closedDates->isClosed($date)) {
            $this->addFlash('notice', sprintf('%s is already closed.', $date->format('D, j M Y')));

            return $this->redirectToRoute('app_admin_closed');
        }

        $this->em->persist(new ClosedDate($date));
        $this->em->flush();

        $paidPax = $this->reservations->paidPaxForDate($date);
        if ($paidPax > 0) {
            $this->addFlash('notice', sprintf(
                'Closed %s — note %d paid guest%s already booked. Move or refund them from the list below.',
                $date->format('D, j M Y'),
                $paidPax,
                1 === $paidPax ? '' : 's'
            ));
        } else {
            $this->addFlash('notice', sprintf('Closed %s.', $date->format('D, j M Y')));
        }

        return $this->redirectToRoute('app_admin_closed');
    }

    #[Route('/admin/closed/{date}/delete', name: 'app_admin_closed_delete', methods: ['POST'], requirements: ['date' => '\d{4}-\d{2}-\d{2}'])]
    public function reopenDate(Request $request, string $date): Response
    {
        $this->isCsrfTokenValid('admin', $request->request->get('_csrf_token'))
            or throw $this->createAccessDeniedException('Invalid CSRF token.');

        $seatingDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date) ?: null;
        if ($seatingDate) {
            $closed = $this->closedDates->findOneBy(['seatingDate' => $seatingDate]);
            if ($closed) {
                $this->em->remove($closed);
                $this->em->flush();
                $this->addFlash('notice', sprintf('Reopened %s.', $seatingDate->format('D, j M Y')));
            }
        }

        return $this->redirectToRoute('app_admin_closed');
    }

    #[Route('/admin/menu', name: 'app_admin_menu', methods: ['GET'])]
    public function menu(): Response
    {
        return $this->render('page/admin_menu.html.twig', [
            'dishes' => $this->menu->findAllOrdered(),
        ]);
    }

    #[Route('/admin/menu', name: 'app_admin_menu_add', methods: ['POST'])]
    public function addDish(Request $request): Response
    {
        $this->isCsrfTokenValid('admin', $request->request->get('_csrf_token'))
            or throw $this->createAccessDeniedException('Invalid CSRF token.');

        $name = trim((string) $request->request->get('name'));
        if ('' === $name || 255 < \strlen($name)) {
            $this->addFlash('error', 'Give the dish a name (up to 255 characters).');

            return $this->redirectToRoute('app_admin_menu');
        }

        $this->em->persist(new MenuItem($name, $this->menu->nextPosition()));
        $this->em->flush();
        $this->addFlash('notice', sprintf('Added “%s”.', $name));

        return $this->redirectToRoute('app_admin_menu');
    }

    #[Route('/admin/menu/{id}/rename', name: 'app_admin_menu_rename', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function renameDish(Request $request, MenuItem $dish): Response
    {
        $this->isCsrfTokenValid('admin', $request->request->get('_csrf_token'))
            or throw $this->createAccessDeniedException('Invalid CSRF token.');

        $name = trim((string) $request->request->get('name'));
        if ('' === $name || 255 < \strlen($name)) {
            $this->addFlash('error', 'Give the dish a name (up to 255 characters).');

            return $this->redirectToRoute('app_admin_menu');
        }

        $dish->setName($name);
        $this->em->flush();
        $this->addFlash('notice', sprintf('Renamed to “%s”.', $name));

        return $this->redirectToRoute('app_admin_menu');
    }

    #[Route('/admin/menu/{id}/delete', name: 'app_admin_menu_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteDish(Request $request, MenuItem $dish): Response
    {
        $this->isCsrfTokenValid('admin', $request->request->get('_csrf_token'))
            or throw $this->createAccessDeniedException('Invalid CSRF token.');

        $name = $dish->getName();
        $this->em->remove($dish);
        $this->em->flush();
        $this->addFlash('notice', sprintf('Removed “%s”.', $name));

        return $this->redirectToRoute('app_admin_menu');
    }

    #[Route('/admin/menu/{id}/move', name: 'app_admin_menu_move', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function moveDish(Request $request, MenuItem $dish): Response
    {
        $this->isCsrfTokenValid('admin', $request->request->get('_csrf_token'))
            or throw $this->createAccessDeniedException('Invalid CSRF token.');

        $direction = 'down' === $request->request->get('direction') ? 1 : -1;
        $dishes = $this->menu->findAllOrdered();
        foreach ($dishes as $i => $other) {
            if ($other->getId() === $dish->getId()) {
                $neighbour = $dishes[$i + $direction] ?? null;
                if ($neighbour) {
                    $position = $dish->getPosition();
                    $dish->setPosition($neighbour->getPosition());
                    $neighbour->setPosition($position);
                    $this->em->flush();
                }
                break;
            }
        }

        return $this->redirectToRoute('app_admin_menu');
    }
}
