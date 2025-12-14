<?php

namespace App\Repository;

use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * Get conversation between two users
     */
    public function getConversation(User $user1, User $user2): array
    {
        return $this->createQueryBuilder('m')
            ->where('(m.sender = :user1 AND m.receiver = :user2) OR (m.sender = :user2 AND m.receiver = :user1)')
            ->setParameter('user1', $user1)
            ->setParameter('user2', $user2)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all conversations for a user
     */
    public function getConversationsForUser(User $user): array
    {
        // Get messages where user is sender
        $sentMessages = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.receiver) as otherUserId')
            ->where('m.sender = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
        
        // Get messages where user is receiver
        $receivedMessages = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.sender) as otherUserId')
            ->where('m.receiver = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
        
        // Combine and get unique user IDs
        $otherUserIds = [];
        foreach ($sentMessages as $msg) {
            $otherUserIds[] = $msg['otherUserId'];
        }
        foreach ($receivedMessages as $msg) {
            $otherUserIds[] = $msg['otherUserId'];
        }
        
        $otherUserIds = array_unique($otherUserIds);
        
        return $otherUserIds;
    }

    /**
     * Get unread message count for a user
     */
    public function getUnreadCount(User $user): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.receiver = :user')
            ->andWhere('m.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Mark messages as read in a conversation
     */
    public function markAsRead(User $currentUser, User $otherUser): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.isRead', true)
            ->where('m.sender = :otherUser')
            ->andWhere('m.receiver = :currentUser')
            ->andWhere('m.isRead = false')
            ->setParameter('otherUser', $otherUser)
            ->setParameter('currentUser', $currentUser)
            ->getQuery()
            ->execute();
    }
}

