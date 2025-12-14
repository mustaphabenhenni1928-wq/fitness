<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\VerificationCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

class EmailVerificationController extends AbstractController
{
    #[Route('/verify-email', name: 'app_verify_email')]
    public function verifyEmail(
        Request $request,
        UserRepository $userRepo,
        VerificationCodeRepository $verificationCodeRepo,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): Response {
        $email = $request->query->get('email') ?? $request->request->get('email');

        if (!$email) {
            $this->addFlash('error', 'Email manquant.');
            return $this->redirectToRoute('app_register');
        }

        $user = $userRepo->findOneBy(['email' => $email]);

        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_register');
        }

        if ($user->isVerified()) {
            $this->addFlash('success', 'Votre email est déjà vérifié. Vous pouvez vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        // Traitement de la soumission du formulaire
        if ($request->isMethod('POST')) {
            $code = $request->request->get('code');

            if (!$code) {
                $this->addFlash('error', 'Veuillez entrer le code de vérification.');
            } else {
                $verificationCode = $verificationCodeRepo->findValidCode($email, $code, 'email_verification');

                if ($verificationCode) {
                    // Marquer le code comme utilisé
                    $verificationCode->setUsed(true);
                    
                    // Vérifier l'utilisateur
                    $user->setIsVerified(true);
                    
                    $em->flush();

                    $this->addFlash('success', 'Votre email a été vérifié avec succès ! Vous pouvez maintenant vous connecter.');
                    return $this->redirectToRoute('app_login');
                } else {
                    $this->addFlash('error', 'Code de vérification invalide ou expiré. Veuillez réessayer.');
                }
            }
        }

        // Demande de renvoi de code
        if ($request->request->get('resend') === '1') {
            // Invalider les anciens codes
            $verificationCodeRepo->invalidateOldCodes($email, 'email_verification');

            // Générer un nouveau code
            $verificationCode = new \App\Entity\VerificationCode();
            $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $verificationCode->setEmail($email);
            $verificationCode->setCode($code);
            $verificationCode->setType('email_verification');

            $em->persist($verificationCode);
            $em->flush();

            try {
                $emailMessage = (new Email())
                    ->from('mzi54794@gmail.com')
                    ->to($email)
                    ->subject('Nouveau code de vérification - Health & Fitness')
                    ->html("
                        <h2>Nouveau code de vérification</h2>
                        <p>Voici votre nouveau code de vérification :</p>
                        <div style='background: #f0f0f0; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0;'>
                            {$code}
                        </div>
                        <p>Ce code est valide pendant 15 minutes.</p>
                    ");

                $mailer->send($emailMessage);

                $this->addFlash('success', 'Un nouveau code de vérification a été envoyé à votre adresse email.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi de l\'email. Veuillez réessayer.');
            }
        }

        return $this->render('security/verify_email.html.twig', [
            'email' => $email,
        ]);
    }
}

