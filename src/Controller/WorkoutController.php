<?php

namespace App\Controller;

use App\Entity\Exercise;
use App\Entity\Workout;
use App\Repository\ExerciseRepository;
use App\Repository\WorkoutRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WorkoutController extends AbstractController
{
    #[Route('/workouts', name: 'app_workouts')]
    public function index(
        ExerciseRepository $exerciseRepo,
        WorkoutRepository $workoutRepo,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $type = $request->query->get('type', '');
        $level = $request->query->get('level', '');
        
        $qb = $exerciseRepo->createQueryBuilder('e');
        
        if ($type) {
            $qb->andWhere('e.type = :type')->setParameter('type', $type);
        }
        if ($level) {
            $qb->andWhere('e.level = :level')->setParameter('level', $level);
        }
        
        $exercises = $qb->getQuery()->getResult();
        
        // Récupérer les workouts de l'utilisateur
        $userWorkouts = $workoutRepo->findBy(
            ['user' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        return $this->render('workout/index.html.twig', [
            'exercises' => $exercises,
            'workouts' => $userWorkouts,
            'selectedType' => $type,
            'selectedLevel' => $level,
        ]);
    }

    #[Route('/workouts/create', name: 'app_workout_create')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ExerciseRepository $exerciseRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $description = $request->request->get('description');
            $type = $request->request->get('type');
            $level = $request->request->get('level');
            $duration = (int) $request->request->get('duration');
            $exerciseIds = $request->request->all('exercises') ?? [];

            if ($name) {
                $workout = new Workout();
                $workout->setName($name);
                $workout->setDescription($description);
                $workout->setUser($this->getUser());
                $workout->setDuration($duration);
                
                // Calculer les calories basées sur les exercices
                $totalCalories = 0;
                foreach ($exerciseIds as $exerciseId) {
                    $exercise = $exerciseRepo->find($exerciseId);
                    if ($exercise) {
                        $workout->addExercise($exercise);
                        $totalCalories += $exercise->getCalories() ?? 0;
                    }
                }
                $workout->setCalories($totalCalories);

                $em->persist($workout);
                $em->flush();

                $this->addFlash('success', 'Programme d\'entraînement créé avec succès !');
                return $this->redirectToRoute('app_workouts');
            }
        }

        $exercises = $exerciseRepo->findAll();

        return $this->render('workout/create.html.twig', [
            'exercises' => $exercises,
        ]);
    }

    #[Route('/workouts/{id}/edit', name: 'app_workout_edit')]
    public function edit(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        WorkoutRepository $workoutRepo,
        ExerciseRepository $exerciseRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $workout = $workoutRepo->find($id);
        
        if (!$workout || $workout->getUser() !== $this->getUser()) {
            $this->addFlash('error', 'Programme d\'entraînement introuvable.');
            return $this->redirectToRoute('app_workouts');
        }

        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $description = $request->request->get('description');
            $duration = (int) $request->request->get('duration');
            $exerciseIds = $request->request->all('exercises') ?? [];

            if ($name) {
                $workout->setName($name);
                $workout->setDescription($description);
                $workout->setDuration($duration);
                
                // Clear existing exercises
                foreach ($workout->getExercises() as $exercise) {
                    $workout->removeExercise($exercise);
                }
                
                // Add new exercises
                $totalCalories = 0;
                foreach ($exerciseIds as $exerciseId) {
                    $exercise = $exerciseRepo->find($exerciseId);
                    if ($exercise) {
                        $workout->addExercise($exercise);
                        $totalCalories += $exercise->getCalories() ?? 0;
                    }
                }
                $workout->setCalories($totalCalories);

                $em->flush();

                $this->addFlash('success', 'Programme d\'entraînement modifié avec succès !');
                return $this->redirectToRoute('app_workouts');
            }
        }

        $exercises = $exerciseRepo->findAll();

        return $this->render('workout/edit.html.twig', [
            'workout' => $workout,
            'exercises' => $exercises,
        ]);
    }

    #[Route('/workouts/{id}/delete', name: 'app_workout_delete', methods: ['POST'])]
    public function delete(
        int $id,
        WorkoutRepository $workoutRepo,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $workout = $workoutRepo->find($id);
        
        if (!$workout || $workout->getUser() !== $this->getUser()) {
            $this->addFlash('error', 'Programme d\'entraînement introuvable.');
            return $this->redirectToRoute('app_workouts');
        }

        $em->remove($workout);
        $em->flush();

        $this->addFlash('success', 'Programme d\'entraînement supprimé avec succès !');
        return $this->redirectToRoute('app_workouts');
    }
}


