<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Repository\BookingRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CoachController extends AbstractController
{
    #[Route('/coach', name: 'app_coach')]
    public function index(BookingRepository $bookingRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_COACH');

        $coach = $this->getUser();
        
        // Récupérer toutes les réservations pour ce coach
        $bookings = $bookingRepo->findBy(
            ['coach' => $coach],
            ['createdAt' => 'DESC']
        );
        
        // Statistiques
        $pendingBookings = $bookingRepo->count(['coach' => $coach, 'status' => 'pending']);
        $acceptedBookings = $bookingRepo->count(['coach' => $coach, 'status' => 'accepted']);
        $rejectedBookings = $bookingRepo->count(['coach' => $coach, 'status' => 'rejected']);
        $totalBookings = count($bookings);

        return $this->render('coach/index.html.twig', [
            'bookings' => $bookings,
            'pendingBookings' => $pendingBookings,
            'acceptedBookings' => $acceptedBookings,
            'rejectedBookings' => $rejectedBookings,
            'totalBookings' => $totalBookings,
        ]);
    }

    #[Route('/coach/bookings/{id}/accept', name: 'app_coach_booking_accept', methods: ['POST'])]
    public function acceptBooking(
        int $id,
        BookingRepository $bookingRepo,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_COACH');

        $booking = $bookingRepo->find($id);
        
        if (!$booking || $booking->getCoach() !== $this->getUser()) {
            $this->addFlash('error', 'Réservation introuvable.');
            return $this->redirectToRoute('app_coach');
        }

        $booking->setStatus('accepted');
        $em->flush();

        $this->addFlash('success', 'Réservation acceptée avec succès !');
        return $this->redirectToRoute('app_coach');
    }

    #[Route('/coach/bookings/{id}/reject', name: 'app_coach_booking_reject', methods: ['POST'])]
    public function rejectBooking(
        int $id,
        BookingRepository $bookingRepo,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_COACH');

        $booking = $bookingRepo->find($id);
        
        if (!$booking || $booking->getCoach() !== $this->getUser()) {
            $this->addFlash('error', 'Réservation introuvable.');
            return $this->redirectToRoute('app_coach');
        }

        $booking->setStatus('rejected');
        $em->flush();

        $this->addFlash('success', 'Réservation refusée.');
        return $this->redirectToRoute('app_coach');
    }

    #[Route('/coach/clients', name: 'app_coach_clients')]
    public function clients(UserRepository $userRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_COACH');

        $coach = $this->getUser();
        
        // Get all users who have selected this coach
        $clients = $userRepo->createQueryBuilder('u')
            ->where('u.coach = :coach')
            ->setParameter('coach', $coach)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('coach/clients.html.twig', [
            'clients' => $clients,
        ]);
    }
}





