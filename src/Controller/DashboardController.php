<?php

namespace App\Controller;

use App\Entity\Meal;
use App\Entity\Workout;
use App\Repository\MealRepository;
use App\Repository\WorkoutRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        EntityManagerInterface $em,
        WorkoutRepository $workoutRepo,
        MealRepository $mealRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        
        // Calculer les statistiques
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $tomorrow = clone $today;
        $tomorrow->modify('+1 day');
        
        // Calories dépensées aujourd'hui
        $workoutsToday = $workoutRepo->createQueryBuilder('w')
            ->where('w.user = :user')
            ->andWhere('w.createdAt >= :today')
            ->andWhere('w.createdAt < :tomorrow')
            ->setParameter('user', $user)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getResult();
        
        $caloriesBurned = 0;
        foreach ($workoutsToday as $workout) {
            $caloriesBurned += $workout->getCalories() ?? 0;
        }
        
        // Calories consommées aujourd'hui
        $mealsToday = $mealRepo->createQueryBuilder('m')
            ->join('m.food', 'f')
            ->where('m.user = :user')
            ->andWhere('m.date >= :today')
            ->andWhere('m.date < :tomorrow')
            ->setParameter('user', $user)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getResult();
        
        $caloriesConsumed = 0;
        $protein = 0;
        $carbs = 0;
        $fat = 0;
        
        foreach ($mealsToday as $meal) {
            $food = $meal->getFood();
            if ($food) {
                $quantity = $meal->getQuantity() / 100; // Convertir en ratio
                $caloriesConsumed += $food->getCalories() * $quantity;
                $protein += $food->getProtein() * $quantity;
                $carbs += $food->getCarbs() * $quantity;
                $fat += $food->getFat() * $quantity;
            }
        }
        
        // Nombre de séances cette semaine
        $weekStart = clone $today;
        $weekStart->modify('monday this week');
        $weekEnd = clone $weekStart;
        $weekEnd->modify('+6 days');
        
        $workoutsThisWeek = $workoutRepo->createQueryBuilder('w')
            ->where('w.user = :user')
            ->andWhere('w.createdAt BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $weekStart)
            ->setParameter('end', $weekEnd)
            ->getQuery()
            ->getResult();
        
        // Dernières activités
        $recentWorkouts = $workoutRepo->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
            5
        );
        
        $recentMeals = $mealRepo->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
            5
        );

        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'caloriesBurned' => $caloriesBurned,
            'caloriesConsumed' => round($caloriesConsumed),
            'protein' => round($protein),
            'carbs' => round($carbs),
            'fat' => round($fat),
            'workoutsThisWeek' => count($workoutsThisWeek),
            'recentWorkouts' => $recentWorkouts,
            'recentMeals' => $recentMeals,
        ]);
    }
}


