<?php

namespace App\Controller;

use App\Repository\MealRepository;
use App\Repository\WorkoutRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StatisticsController extends AbstractController
{
    #[Route('/statistics', name: 'app_statistics')]
    public function index(
        WorkoutRepository $workoutRepo,
        MealRepository $mealRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        
        // Statistiques générales
        $allWorkouts = $workoutRepo->findBy(['user' => $user]);
        $allMeals = $mealRepo->findBy(['user' => $user]);
        
        $totalCaloriesBurned = 0;
        $totalCaloriesConsumed = 0;
        $totalWorkouts = count($allWorkouts);
        $totalMeals = count($allMeals);
        
        foreach ($allWorkouts as $workout) {
            $totalCaloriesBurned += $workout->getCalories() ?? 0;
        }
        
        foreach ($allMeals as $meal) {
            $food = $meal->getFood();
            if ($food) {
                $quantity = $meal->getQuantity() / 100;
                $totalCaloriesConsumed += $food->getCalories() * $quantity;
            }
        }
        
        // Statistiques par semaine (7 dernières semaines)
        $weeklyStats = [];
        for ($i = 6; $i >= 0; $i--) {
            $weekStart = new \DateTime("-$i weeks monday");
            $weekStart->setTime(0, 0, 0);
            $weekEnd = clone $weekStart;
            $weekEnd->modify('+6 days');
            $weekEnd->setTime(23, 59, 59);
            
            $weekWorkouts = $workoutRepo->createQueryBuilder('w')
                ->where('w.user = :user')
                ->andWhere('w.createdAt >= :start')
                ->andWhere('w.createdAt <= :end')
                ->setParameter('user', $user)
                ->setParameter('start', $weekStart)
                ->setParameter('end', $weekEnd)
                ->getQuery()
                ->getResult();
            
            $weekMeals = $mealRepo->createQueryBuilder('m')
                ->join('m.food', 'f')
                ->where('m.user = :user')
                ->andWhere('m.date >= :start')
                ->andWhere('m.date <= :end')
                ->setParameter('user', $user)
                ->setParameter('start', $weekStart)
                ->setParameter('end', $weekEnd)
                ->getQuery()
                ->getResult();
            
            $weekCaloriesBurned = 0;
            $weekCaloriesConsumed = 0;
            
            foreach ($weekWorkouts as $workout) {
                $weekCaloriesBurned += $workout->getCalories() ?? 0;
            }
            
            foreach ($weekMeals as $meal) {
                $food = $meal->getFood();
                if ($food) {
                    $quantity = $meal->getQuantity() / 100;
                    $weekCaloriesConsumed += $food->getCalories() * $quantity;
                }
            }
            
            $weeklyStats[] = [
                'week' => $weekStart->format('d/m'),
                'caloriesBurned' => $weekCaloriesBurned,
                'caloriesConsumed' => round($weekCaloriesConsumed),
                'workouts' => count($weekWorkouts),
                'meals' => count($weekMeals),
            ];
        }
        
        // Statistiques par mois (6 derniers mois)
        $monthlyStats = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = new \DateTime("first day of -$i months");
            $monthStart->setTime(0, 0, 0);
            $monthEnd = new \DateTime("last day of -$i months");
            $monthEnd->setTime(23, 59, 59);
            
            $monthWorkouts = $workoutRepo->createQueryBuilder('w')
                ->where('w.user = :user')
                ->andWhere('w.createdAt >= :start')
                ->andWhere('w.createdAt <= :end')
                ->setParameter('user', $user)
                ->setParameter('start', $monthStart)
                ->setParameter('end', $monthEnd)
                ->getQuery()
                ->getResult();
            
            $monthMeals = $mealRepo->createQueryBuilder('m')
                ->join('m.food', 'f')
                ->where('m.user = :user')
                ->andWhere('m.date >= :start')
                ->andWhere('m.date <= :end')
                ->setParameter('user', $user)
                ->setParameter('start', $monthStart)
                ->setParameter('end', $monthEnd)
                ->getQuery()
                ->getResult();
            
            $monthCaloriesBurned = 0;
            $monthCaloriesConsumed = 0;
            
            foreach ($monthWorkouts as $workout) {
                $monthCaloriesBurned += $workout->getCalories() ?? 0;
            }
            
            foreach ($monthMeals as $meal) {
                $food = $meal->getFood();
                if ($food) {
                    $quantity = $meal->getQuantity() / 100;
                    $monthCaloriesConsumed += $food->getCalories() * $quantity;
                }
            }
            
            $monthlyStats[] = [
                'month' => $monthStart->format('M Y'),
                'caloriesBurned' => $monthCaloriesBurned,
                'caloriesConsumed' => round($monthCaloriesConsumed),
                'workouts' => count($monthWorkouts),
                'meals' => count($monthMeals),
            ];
        }

        return $this->render('statistics/index.html.twig', [
            'totalCaloriesBurned' => $totalCaloriesBurned,
            'totalCaloriesConsumed' => round($totalCaloriesConsumed),
            'totalWorkouts' => $totalWorkouts,
            'totalMeals' => $totalMeals,
            'weeklyStats' => $weeklyStats,
            'monthlyStats' => $monthlyStats,
        ]);
    }
}





