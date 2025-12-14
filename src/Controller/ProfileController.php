<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Security;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(
        Request $request, 
        EntityManagerInterface $em, 
        UserRepository $userRepo,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        // Only allow regular users (not coaches) to select a coach
        $coaches = [];
        if (!in_array('ROLE_COACH', $user->getRoles())) {
            $coaches = $userRepo->createQueryBuilder('u')
                ->where('u.roles LIKE :role')
                ->setParameter('role', '%ROLE_COACH%')
                ->orderBy('u.email', 'ASC')
                ->getQuery()
                ->getResult();
        }
        
        if ($request->isMethod('POST')) {
            // Handle password change
            if ($request->request->get('action') === 'change_password') {
                $currentPassword = $request->request->get('current_password');
                $newPassword = $request->request->get('new_password');
                $confirmPassword = $request->request->get('confirm_password');
                
                if (!$currentPassword || !$newPassword || !$confirmPassword) {
                    $this->addFlash('error', 'Tous les champs sont obligatoires pour changer le mot de passe.');
                } elseif (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                    $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
                } elseif (strlen($newPassword) < 8) {
                    $this->addFlash('error', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
                } elseif ($newPassword !== $confirmPassword) {
                    $this->addFlash('error', 'Les nouveaux mots de passe ne correspondent pas.');
                } else {
                    $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                    $em->flush();
                    $this->addFlash('success', 'Mot de passe modifié avec succès !');
                }
                return $this->redirectToRoute('app_profile');
            }
            
            // Handle profile update
            $user->setAge($request->request->get('age') ? (int) $request->request->get('age') : null);
            $user->setHeight($request->request->get('height') ? (int) $request->request->get('height') : null);
            $user->setWeight($request->request->get('weight') ? (float) $request->request->get('weight') : null);
            $user->setGoal($request->request->get('goal'));
            
            // Handle coach selection
            if (!in_array('ROLE_COACH', $user->getRoles())) {
                $coachId = $request->request->get('coach');
                if ($coachId) {
                    $coach = $userRepo->find($coachId);
                    if ($coach && in_array('ROLE_COACH', $coach->getRoles())) {
                        $user->setCoach($coach);
                    }
                } else {
                    $user->setCoach(null);
                }
            }

            $em->flush();

            $this->addFlash('success', 'Profil mis à jour avec succès !');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'coaches' => $coaches,
        ]);
    }
}


