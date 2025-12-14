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

class BookingController extends AbstractController
{
    #[Route('/bookings/create', name: 'app_booking_create')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($request->isMethod('POST')) {
            $coachId = $request->request->get('coach_id');
            $date = $request->request->get('date') ? new \DateTime($request->request->get('date')) : new \DateTime();
            $time = $request->request->get('time') ? new \DateTime($request->request->get('time')) : new \DateTime();
            $notes = $request->request->get('notes');

            if ($coachId) {
                $coach = $userRepo->find($coachId);
                if ($coach && in_array('ROLE_COACH', $coach->getRoles())) {
                    $booking = new Booking();
                    $booking->setClient($this->getUser());
                    $booking->setCoach($coach);
                    $booking->setDate($date);
                    $booking->setTime($time);
                    $booking->setNotes($notes);
                    $booking->setStatus('pending');

                    $em->persist($booking);
                    $em->flush();

                    $this->addFlash('success', 'Réservation créée avec succès !');
                    return $this->redirectToRoute('app_dashboard');
                }
            }
        }

        // Récupérer tous les coaches
        $coaches = $userRepo->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_COACH%')
            ->getQuery()
            ->getResult();

        return $this->render('booking/create.html.twig', [
            'coaches' => $coaches,
        ]);
    }

    #[Route('/bookings', name: 'app_bookings')]
    public function index(BookingRepository $bookingRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        
        // Récupérer les réservations de l'utilisateur (en tant que client)
        $bookings = $bookingRepo->findBy(
            ['client' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('booking/index.html.twig', [
            'bookings' => $bookings,
        ]);
    }

    #[Route('/bookings/{id}/delete', name: 'app_booking_delete', methods: ['POST'])]
    public function delete(
        int $id,
        BookingRepository $bookingRepo,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $booking = $bookingRepo->find($id);
        
        if (!$booking || $booking->getClient() !== $this->getUser()) {
            $this->addFlash('error', 'Réservation introuvable.');
            return $this->redirectToRoute('app_bookings');
        }

        $em->remove($booking);
        $em->flush();

        $this->addFlash('success', 'Réservation supprimée avec succès !');
        return $this->redirectToRoute('app_bookings');
    }
}

