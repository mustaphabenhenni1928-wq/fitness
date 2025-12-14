<?php

namespace App\Repository;

use App\Entity\VerificationCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class VerificationCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VerificationCode::class);
    }

    public function findValidCode(string $email, string $code, string $type): ?VerificationCode
    {
        return $this->createQueryBuilder('vc')
            ->where('vc.email = :email')
            ->andWhere('vc.code = :code')
            ->andWhere('vc.type = :type')
            ->andWhere('vc.used = false')
            ->andWhere('vc.expiresAt > :now')
            ->setParameter('email', $email)
            ->setParameter('code', $code)
            ->setParameter('type', $type)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('vc.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function invalidateOldCodes(string $email, string $type): void
    {
        $this->createQueryBuilder('vc')
            ->update()
            ->set('vc.used', true)
            ->where('vc.email = :email')
            ->andWhere('vc.type = :type')
            ->andWhere('vc.used = false')
            ->setParameter('email', $email)
            ->setParameter('type', $type)
            ->getQuery()
            ->execute();
    }
}

