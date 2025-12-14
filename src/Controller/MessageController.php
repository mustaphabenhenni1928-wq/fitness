<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MessageController extends AbstractController
{
    #[Route('/messages', name: 'app_messages')]
    public function index(MessageRepository $messageRepo, UserRepository $userRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $user = $this->getUser();
        $otherUserIds = $messageRepo->getConversationsForUser($user);
        
        // If user is not a coach and has a coach but no conversations yet, redirect to start conversation
        if (!in_array('ROLE_COACH', $user->getRoles()) && $user->getCoach() && empty($otherUserIds)) {
            return $this->redirectToRoute('app_message_conversation', ['id' => $user->getCoach()->getId()]);
        }
        
        // Get actual user objects for each conversation
        $conversationList = [];
        foreach ($otherUserIds as $otherUserId) {
            $otherUser = $userRepo->find($otherUserId);
            if ($otherUser) {
                $lastMessage = $messageRepo->createQueryBuilder('m')
                    ->where('(m.sender = :user1 AND m.receiver = :user2) OR (m.sender = :user2 AND m.receiver = :user1)')
                    ->setParameter('user1', $user)
                    ->setParameter('user2', $otherUser)
                    ->orderBy('m.createdAt', 'DESC')
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();
                
                $unreadCount = $messageRepo->createQueryBuilder('m')
                    ->select('COUNT(m.id)')
                    ->where('m.sender = :otherUser')
                    ->andWhere('m.receiver = :user')
                    ->andWhere('m.isRead = false')
                    ->setParameter('otherUser', $otherUser)
                    ->setParameter('user', $user)
                    ->getQuery()
                    ->getSingleScalarResult();
                
                $conversationList[] = [
                    'user' => $otherUser,
                    'lastMessage' => $lastMessage,
                    'unreadCount' => $unreadCount
                ];
            }
        }
        
        // Sort by last message date
        usort($conversationList, function($a, $b) {
            $dateA = $a['lastMessage'] ? $a['lastMessage']->getCreatedAt() : new \DateTimeImmutable('1970-01-01');
            $dateB = $b['lastMessage'] ? $b['lastMessage']->getCreatedAt() : new \DateTimeImmutable('1970-01-01');
            return $dateB <=> $dateA;
        });
        
        return $this->render('message/index.html.twig', [
            'conversations' => $conversationList,
        ]);
    }

    #[Route('/messages/{id}', name: 'app_message_conversation', requirements: ['id' => '\d+'])]
    public function conversation(
        int $id,
        UserRepository $userRepo,
        MessageRepository $messageRepo,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $currentUser = $this->getUser();
        $otherUser = $userRepo->find($id);
        
        if (!$otherUser) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_messages');
        }
        
        // Verify that the user can message this person
        // Users can only message their coach, or coaches can message their clients
        $canMessage = false;
        if (in_array('ROLE_COACH', $currentUser->getRoles())) {
            // Coach can message any user who has selected them as coach
            $canMessage = ($otherUser->getCoach() === $currentUser);
        } else {
            // Regular user can only message their coach
            $canMessage = ($currentUser->getCoach() === $otherUser);
        }
        
        if (!$canMessage) {
            $this->addFlash('error', 'Vous ne pouvez pas envoyer de message à cet utilisateur.');
            return $this->redirectToRoute('app_messages');
        }
        
        // Mark messages as read
        $messageRepo->markAsRead($currentUser, $otherUser);
        
        // Get conversation messages
        $messages = $messageRepo->getConversation($currentUser, $otherUser);
        
        // Handle message sending
        if ($request->isMethod('POST')) {
            $content = $request->request->get('content');
            if (!empty(trim($content))) {
                $message = new Message();
                $message->setSender($currentUser);
                $message->setReceiver($otherUser);
                $message->setContent(trim($content));
                
                $em->persist($message);
                $em->flush();
                
                return $this->redirectToRoute('app_message_conversation', ['id' => $id]);
            }
        }
        
        return $this->render('message/conversation.html.twig', [
            'otherUser' => $otherUser,
            'messages' => $messages,
        ]);
    }
}

