<?php

namespace App\Controller;

use App\Entity\VerificationCode;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\VerificationCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class PasswordResetController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepo,
        VerificationCodeRepository $verificationCodeRepo,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): Response {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            
            if (!$email) {
                $this->addFlash('error', 'Veuillez entrer votre adresse email.');
            } else {
                $user = $userRepo->findOneBy(['email' => $email]);
                
                if (!$user) {
                    // Don't reveal if user exists for security
                    $this->addFlash('success', 'Si un compte existe avec cet email, un code de réinitialisation a été envoyé.');
                    return $this->render('security/forgot_password.html.twig');
                }
                
                // Invalidate old codes
                $verificationCodeRepo->invalidateOldCodes($email, 'password_reset');
                
                // Generate new code
                $verificationCode = new VerificationCode();
                $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                $verificationCode->setEmail($email);
                $verificationCode->setCode($code);
                $verificationCode->setType('password_reset');
                
                $em->persist($verificationCode);
                $em->flush();
                
                try {
                    $emailMessage = (new Email())
                        ->from('mzi54794@gmail.com')
                        ->to($email)
                        ->subject('Réinitialisation de votre mot de passe - Health & Fitness')
                        ->html("
                            <h2>Réinitialisation de mot de passe</h2>
                            <p>Vous avez demandé à réinitialiser votre mot de passe. Utilisez le code suivant :</p>
                            <div style='background: #f0f0f0; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0;'>
                                {$code}
                            </div>
                            <p>Ce code est valide pendant 15 minutes.</p>
                            <p>Si vous n'avez pas demandé cette réinitialisation, veuillez ignorer cet email.</p>
                        ");
                    
                    $mailer->send($emailMessage);
                    
                    $this->addFlash('success', 'Un code de réinitialisation a été envoyé à votre adresse email.');
                    return $this->redirectToRoute('app_reset_password', ['email' => $email]);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi de l\'email. Veuillez réessayer.');
                }
            }
        }
        
        return $this->render('security/forgot_password.html.twig');
    }

    #[Route('/reset-password', name: 'app_reset_password')]
    public function resetPassword(
        Request $request,
        UserRepository $userRepo,
        VerificationCodeRepository $verificationCodeRepo,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $email = $request->query->get('email') ?? $request->request->get('email');
        
        if (!$email) {
            $this->addFlash('error', 'Email manquant.');
            return $this->redirectToRoute('app_forgot_password');
        }
        
        $user = $userRepo->findOneBy(['email' => $email]);
        
        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_forgot_password');
        }
        
        if ($request->isMethod('POST')) {
            $code = $request->request->get('code');
            $newPassword = $request->request->get('new_password');
            $confirmPassword = $request->request->get('confirm_password');
            
            if (!$code || !$newPassword || !$confirmPassword) {
                $this->addFlash('error', 'Tous les champs sont obligatoires.');
            } elseif (strlen($newPassword) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');
            } elseif ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
            } else {
                $verificationCode = $verificationCodeRepo->findValidCode($email, $code, 'password_reset');
                
                if ($verificationCode) {
                    // Mark code as used
                    $verificationCode->setUsed(true);
                    
                    // Update password
                    $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                    
                    $em->flush();
                    
                    $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès ! Vous pouvez maintenant vous connecter.');
                    return $this->redirectToRoute('app_login');
                } else {
                    $this->addFlash('error', 'Code de vérification invalide ou expiré. Veuillez réessayer.');
                }
            }
        }
        
        return $this->render('security/reset_password.html.twig', [
            'email' => $email,
        ]);
    }
}

