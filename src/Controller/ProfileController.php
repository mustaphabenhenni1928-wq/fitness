<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use Symfony\Component\Security\Core\Security;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
//dd($this->getUser());
        if ($request->isMethod('POST')) {
            $user->setAge($request->request->get('age') ? (int) $request->request->get('age') : null);
            $user->setHeight($request->request->get('height') ? (int) $request->request->get('height') : null);
            $user->setWeight($request->request->get('weight') ? (float) $request->request->get('weight') : null);
            $user->setGoal($request->request->get('goal'));

            $em->flush();

            $this->addFlash('success', 'Profil mis à jour avec succès !');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
        ]);
    }
}


