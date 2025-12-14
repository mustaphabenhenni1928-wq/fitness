<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\VerificationCode;
use App\Repository\VerificationCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        VerificationCodeRepository $verificationCodeRepo
    ): Response {
        // Si l'utilisateur est déjà connecté, rediriger vers le dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }
        
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');
            $agreeTerms = $request->request->get('agree_terms');
            $roleChoice = $request->request->get('role');

            // Validation
            if (empty($email) || empty($password)) {
                $this->addFlash('error', 'Tous les champs sont obligatoires.');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'L\'adresse email n\'est pas valide.');
            } elseif (strlen($password) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');
            } elseif ($password !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
            } elseif (!$agreeTerms) {
                $this->addFlash('error', 'Vous devez accepter les conditions d\'utilisation.');
            } else {
                // Vérifier si l'utilisateur existe déjà
                $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                
                if ($existingUser) {
                    $this->addFlash('error', 'Un compte avec cet email existe déjà.');
                } else {
                    // Créer le nouvel utilisateur
                    $user = new User();
                    $user->setEmail($email);
                    $user->setPassword($passwordHasher->hashPassword($user, $password));
                    $user->setIsVerified(false); // Non vérifié par défaut
                    
                    // Gérer les rôles selon le choix de l'utilisateur
                    if ($roleChoice === 'ROLE_COACH') {
                        $user->setRoles(['ROLE_COACH', 'ROLE_USER']);
                    } elseif ($roleChoice === 'ROLE_ADMIN') {
                        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
                    } else {
                        // Par défaut, rôle utilisateur
                        $user->setRoles(['ROLE_USER']);
                    }

                    $entityManager->persist($user);
                    $entityManager->flush();

                    // Générer un code de vérification
                    $verificationCode = new VerificationCode();
                    $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                    $verificationCode->setEmail($email);
                    $verificationCode->setCode($code);
                    $verificationCode->setType('email_verification');

                    // Invalider les anciens codes pour cet email
                    $verificationCodeRepo->invalidateOldCodes($email, 'email_verification');

                    $entityManager->persist($verificationCode);
                    $entityManager->flush();

                    // Envoyer l'email avec le code
                    try {
                        $emailMessage = (new Email())
                            ->from('mzi54794@gmail.com')
                            ->to($email)
                            ->subject('Vérification de votre compte Health & Fitness')
                            ->html("
                                <h2>Bienvenue sur Health & Fitness !</h2>
                                <p>Merci de vous être inscrit. Pour activer votre compte, veuillez utiliser le code de vérification suivant :</p>
                                <div style='background: #f0f0f0; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0;'>
                                    {$code}
                                </div>
                                <p>Ce code est valide pendant 15 minutes.</p>
                                <p>Si vous n'avez pas créé de compte, veuillez ignorer cet email.</p>
                            ");

                        $mailer->send($emailMessage);

                        $this->addFlash('success', 'Inscription réussie ! Un code de vérification a été envoyé à votre adresse email.');
                        return $this->redirectToRoute('app_verify_email', ['email' => $email]);
                    } catch (\Exception $e) {
                        // Si l'envoi d'email échoue, on supprime l'utilisateur créé
                        $entityManager->remove($user);
                        $entityManager->remove($verificationCode);
                        $entityManager->flush();
                        
                        $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi de l\'email. Veuillez réessayer.');
                    }
                }
            }
        }

        return $this->render('security/register.html.twig');
    }
}

