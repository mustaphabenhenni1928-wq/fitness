<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

class StaticController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
    public function contact(Request $request, MailerInterface $mailer): Response
    {
        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $email = $request->request->get('email');
            $subject = $request->request->get('subject');
            $message = $request->request->get('message');

            if ($name && $email && $subject && $message) {
                try {
                    $emailMessage = (new Email())
                        ->from($email)
                        ->to('contact@healthfitness.com')
                        ->subject('Contact: ' . $subject)
                        ->html("
                            <h2>Nouveau message de contact</h2>
                            <p><strong>Nom:</strong> {$name}</p>
                            <p><strong>Email:</strong> {$email}</p>
                            <p><strong>Sujet:</strong> {$subject}</p>
                            <p><strong>Message:</strong></p>
                            <p>{$message}</p>
                        ");

                    $mailer->send($emailMessage);

                    $this->addFlash('success', 'Votre message a été envoyé avec succès ! Nous vous répondrons dans les plus brefs délais.');
                    return $this->redirectToRoute('app_contact');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi du message. Veuillez réessayer.');
                }
            } else {
                $this->addFlash('error', 'Veuillez remplir tous les champs.');
            }
        }

        return $this->render('static/contact.html.twig');
    }

    #[Route('/faq', name: 'app_faq')]
    public function faq(): Response
    {
        $faqs = [
            [
                'question' => 'Comment créer un compte ?',
                'answer' => 'Cliquez sur "Inscription" dans le menu, remplissez le formulaire avec votre email et un mot de passe sécurisé (minimum 8 caractères), puis validez votre email avec le code reçu.'
            ],
            [
                'question' => 'Comment suivre mes entraînements ?',
                'answer' => 'Allez dans la section "Entraînements", parcourez les exercices disponibles, filtrez par type ou niveau, puis créez votre programme personnalisé en sélectionnant les exercices souhaités.'
            ],
            [
                'question' => 'Comment enregistrer mes repas ?',
                'answer' => 'Dans la section "Nutrition", cliquez sur "Ajouter un repas", sélectionnez l\'aliment, indiquez la quantité et le type de repas (petit-déjeuner, déjeuner, dîner, collation).'
            ],
            [
                'question' => 'Comment voir mes statistiques ?',
                'answer' => 'La page "Statistiques" affiche vos calories brûlées et consommées, le nombre de séances d\'entraînement, et des graphiques de progression sur plusieurs semaines et mois.'
            ],
            [
                'question' => 'Comment réserver un coach ?',
                'answer' => 'Si vous souhaitez être accompagné par un coach, créez une réservation dans la section "Réservations" en sélectionnant un coach disponible et en choisissant une date et heure.'
            ],
            [
                'question' => 'J\'ai oublié mon mot de passe, que faire ?',
                'answer' => 'Sur la page de connexion, cliquez sur "Mot de passe oublié", entrez votre email, et vous recevrez un code de réinitialisation par email.'
            ],
            [
                'question' => 'Comment modifier mon profil ?',
                'answer' => 'Allez dans "Profil" depuis le menu, vous pouvez mettre à jour votre âge, taille, poids et objectif de fitness.'
            ],
            [
                'question' => 'Les données sont-elles sécurisées ?',
                'answer' => 'Oui, toutes vos données sont cryptées et stockées de manière sécurisée. Nous respectons votre vie privée et ne partageons jamais vos informations personnelles.'
            ]
        ];

        return $this->render('static/faq.html.twig', [
            'faqs' => $faqs,
        ]);
    }

    #[Route('/a-propos', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('static/about.html.twig');
    }

    #[Route('/support', name: 'app_support')]
    public function support(): Response
    {
        return $this->render('static/support.html.twig');
    }
}

