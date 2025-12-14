<?php

namespace App\DataFixtures;

use App\Entity\Exercise;
use App\Entity\Food;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $admin->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $manager->persist($admin);

      
        $coach = new User();
        $coach->setEmail('coach@example.com');
        $coach->setPassword($this->passwordHasher->hashPassword($coach, 'coach123'));
        $coach->setRoles(['ROLE_COACH', 'ROLE_USER']);
        $manager->persist($coach);

        // Créer un utilisateur normal
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'user123'));
        $user->setRoles(['ROLE_USER']);
        $manager->persist($user);

        // Créer des exercices
        $exercises = [
            ['name' => 'Squats', 'type' => 'Musculation', 'level' => 'Intermédiaire', 'duration' => 30, 'calories' => 100],
            ['name' => 'Course à pied', 'type' => 'Cardio', 'level' => 'Débutant', 'duration' => 30, 'calories' => 300],
            ['name' => 'Pompes', 'type' => 'Musculation', 'level' => 'Intermédiaire', 'duration' => 15, 'calories' => 50],
            ['name' => 'Yoga Matinal', 'type' => 'Yoga', 'level' => 'Débutant', 'duration' => 25, 'calories' => 150],
            ['name' => 'HIIT Training', 'type' => 'Cardio', 'level' => 'Avancé', 'duration' => 20, 'calories' => 300],
            ['name' => 'Musculation Jambes', 'type' => 'Musculation', 'level' => 'Intermédiaire', 'duration' => 60, 'calories' => 500],
        ];

        foreach ($exercises as $exData) {
            $exercise = new Exercise();
            $exercise->setName($exData['name']);
            $exercise->setType($exData['type']);
            $exercise->setLevel($exData['level']);
            $exercise->setDuration($exData['duration']);
            $exercise->setCalories($exData['calories']);
            $exercise->setDescription('Description de l\'exercice ' . $exData['name']);
            $manager->persist($exercise);
        }

        // Créer des aliments
        $foods = [
            ['name' => 'Poulet grillé', 'calories' => 165, 'protein' => 31, 'carbs' => 0, 'fat' => 3.6],
            ['name' => 'Riz complet', 'calories' => 130, 'protein' => 2.7, 'carbs' => 28, 'fat' => 0.3],
            ['name' => 'Œufs', 'calories' => 155, 'protein' => 13, 'carbs' => 1.1, 'fat' => 11],
            ['name' => 'Brocolis', 'calories' => 25, 'protein' => 3, 'carbs' => 5, 'fat' => 0.4],
            ['name' => 'Saumon', 'calories' => 208, 'protein' => 20, 'carbs' => 0, 'fat' => 12],
            ['name' => 'Pomme', 'calories' => 52, 'protein' => 0.3, 'carbs' => 14, 'fat' => 0.2],
            ['name' => 'Amandes', 'calories' => 579, 'protein' => 21, 'carbs' => 22, 'fat' => 50],
            ['name' => 'Avocat', 'calories' => 160, 'protein' => 2, 'carbs' => 9, 'fat' => 15],
        ];

        foreach ($foods as $foodData) {
            $food = new Food();
            $food->setName($foodData['name']);
            $food->setCalories($foodData['calories']);
            $food->setProtein($foodData['protein']);
            $food->setCarbs($foodData['carbs']);
            $food->setFat($foodData['fat']);
            $food->setUnit('g');
            $food->setQuantity(100);
            $manager->persist($food);
        }

        $manager->flush();
    }
}




