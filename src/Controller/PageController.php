<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PageController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        return $this->render('page/home.html.twig');
    }

    #[Route('/visit', name: 'app_visit')]
    public function visit(): Response
    {
        return $this->render('page/visit.html.twig');
    }

    #[Route('/legal/privacy', name: 'app_legal_privacy')]
    public function privacy(): Response
    {
        return $this->render('page/legal_privacy.html.twig');
    }

    #[Route('/legal/booking-terms', name: 'app_legal_terms')]
    public function bookingTerms(): Response
    {
        return $this->render('page/legal_terms.html.twig');
    }
}
