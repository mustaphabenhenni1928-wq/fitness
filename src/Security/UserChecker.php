<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte n\'a pas été vérifié. Veuillez vérifier votre email avant de vous connecter.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Pas de vérification post-authentification nécessaire
    }
}

