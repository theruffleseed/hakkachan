<?php

namespace App\Controller;

use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin', methods: ['GET'])]
    public function __invoke(ReservationRepository $reservations): Response
    {
        $bookings = $reservations->findPaidFrom(new \DateTimeImmutable('today'));

        $pax = 0;
        foreach ($bookings as $booking) {
            $pax += $booking->getPax();
        }

        return $this->render('page/admin.html.twig', [
            'bookings' => $bookings,
            'totalPax' => $pax,
        ]);
    }
}
