<?php

namespace App\Controller;

use App\Entity\Food;
use App\Entity\Meal;
use App\Repository\FoodRepository;
use App\Repository\MealRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class NutritionController extends AbstractController
{
    #[Route('/nutrition', name: 'app_nutrition')]
    public function index(MealRepository $mealRepo, FoodRepository $foodRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        // Récupérer les repas d'aujourd'hui
        $meals = $mealRepo->createQueryBuilder('m')
            ->join('m.food', 'f')
            ->where('m.user = :user')
            ->andWhere('m.date = :today')
            ->setParameter('user', $user)
            ->setParameter('today', $today)
            ->orderBy('m.time', 'ASC')
            ->getQuery()
            ->getResult();

        // Calculer les totaux
        $totalCalories = 0;
        $totalProtein = 0;
        $totalCarbs = 0;
        $totalFat = 0;

        $mealsByType = [];
        foreach ($meals as $meal) {
            $food = $meal->getFood();
            $quantity = $meal->getQuantity() / 100;
            
            $calories = $food->getCalories() * $quantity;
            $protein = $food->getProtein() * $quantity;
            $carbs = $food->getCarbs() * $quantity;
            $fat = $food->getFat() * $quantity;

            $totalCalories += $calories;
            $totalProtein += $protein;
            $totalCarbs += $carbs;
            $totalFat += $fat;

            $mealType = $meal->getMealType();
            if (!isset($mealsByType[$mealType])) {
                $mealsByType[$mealType] = [
                    'meals' => [],
                    'totalCalories' => 0,
                ];
            }
            $mealsByType[$mealType]['meals'][] = $meal;
            $mealsByType[$mealType]['totalCalories'] += $calories;
        }

        return $this->render('nutrition/index.html.twig', [
            'mealsByType' => $mealsByType,
            'totalCalories' => round($totalCalories),
            'totalProtein' => round($totalProtein),
            'totalCarbs' => round($totalCarbs),
            'totalFat' => round($totalFat),
            'today' => $today,
        ]);
    }

    #[Route('/nutrition/add', name: 'app_nutrition_add')]
    public function add(Request $request, EntityManagerInterface $em, FoodRepository $foodRepo): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($request->isMethod('POST')) {
            $foodId = $request->request->get('food_id');
            $mealType = $request->request->get('meal_type');
           // dd($mealType);
            $quantity = (float) $request->request->get('quantity', 100);
            $date = $request->request->get('date') ? new \DateTime($request->request->get('date')) : new \DateTime();
            $time = $request->request->get('time') ? new \DateTime($request->request->get('time')) : new \DateTime();
            if ($foodId && $mealType) {
                $food = $foodRepo->find($foodId);
                if ($food) {
                    $meal = new Meal();
                    $meal->setUser($this->getUser());
                    $meal->setFood($food);
                    $meal->setMealType($mealType);
                    $meal->setQuantity($quantity);
                    $meal->setDate($date);
                    $meal->setTime($time);

                    $em->persist($meal);
                    $em->flush();
                    $this->addFlash('success', 'Repas ajouté avec succès !');
                    return $this->redirectToRoute('app_nutrition');
                }
            }
        }

        $foods = $foodRepo->findAll();
        return $this->render('nutrition/add.html.twig', [
            'foods' => $foods,
        ]);
    }

    #[Route('/nutrition/{id}/edit', name: 'app_meal_edit')]
    public function edit(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        MealRepository $mealRepo,
        FoodRepository $foodRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $meal = $mealRepo->find($id);
        
        if (!$meal || $meal->getUser() !== $this->getUser()) {
            $this->addFlash('error', 'Repas introuvable.');
            return $this->redirectToRoute('app_nutrition');
        }

        if ($request->isMethod('POST')) {
            $foodId = $request->request->get('food_id');
            $mealType = $request->request->get('meal_type');
            $quantity = (float) $request->request->get('quantity', 100);
            $date = $request->request->get('date') ? new \DateTime($request->request->get('date')) : new \DateTime();
            $time = $request->request->get('time') ? new \DateTime($request->request->get('time')) : new \DateTime();
            
            if ($foodId && $mealType) {
                $food = $foodRepo->find($foodId);
                if ($food) {
                    $meal->setFood($food);
                    $meal->setMealType($mealType);
                    $meal->setQuantity($quantity);
                    $meal->setDate($date);
                    $meal->setTime($time);

                    $em->flush();
                    $this->addFlash('success', 'Repas modifié avec succès !');
                    return $this->redirectToRoute('app_nutrition');
                }
            }
        }

        $foods = $foodRepo->findAll();
        return $this->render('nutrition/edit.html.twig', [
            'meal' => $meal,
            'foods' => $foods,
        ]);
    }

    #[Route('/nutrition/{id}/delete', name: 'app_meal_delete', methods: ['POST'])]
    public function delete(
        int $id,
        MealRepository $mealRepo,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $meal = $mealRepo->find($id);
        
        if (!$meal || $meal->getUser() !== $this->getUser()) {
            $this->addFlash('error', 'Repas introuvable.');
            return $this->redirectToRoute('app_nutrition');
        }

        $em->remove($meal);
        $em->flush();

        $this->addFlash('success', 'Repas supprimé avec succès !');
        return $this->redirectToRoute('app_nutrition');
    }
}


