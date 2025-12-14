<?php

namespace App\Controller;

use App\Entity\Exercise;
use App\Entity\Food;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\ExerciseRepository;
use App\Repository\FoodRepository;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    public function index(
        UserRepository $userRepo,
        ExerciseRepository $exerciseRepo,
        BookingRepository $bookingRepo,
        OrderRepository $orderRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $stats = [
            'users' => $userRepo->count([]),
            'exercises' => $exerciseRepo->count([]),
            'bookings' => $bookingRepo->count([]),
            'orders' => $orderRepo->count([]),
        ];

        return $this->render('admin/index.html.twig', [
            'stats' => $stats,
        ]);
    }

    #[Route('/admin/users', name: 'app_admin_users')]
    public function users(
        UserRepository $userRepo,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST') && $request->request->get('action') === 'update_role') {
            $id = $request->request->get('id');
            $role = $request->request->get('role');
            $user = $userRepo->find($id);
            if ($user && $role) {
                $roles = [$role];
                if ($role === 'ROLE_ADMIN') {
                    $roles = ['ROLE_ADMIN', 'ROLE_USER'];
                } elseif ($role === 'ROLE_COACH') {
                    $roles = ['ROLE_COACH', 'ROLE_USER'];
                }
                $user->setRoles($roles);
                $em->flush();
                $this->addFlash('success', 'Rôle de l\'utilisateur mis à jour !');
            }
            return $this->redirectToRoute('app_admin_users');
        }

        $users = $userRepo->findAll();

        return $this->render('admin/users.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/admin/exercises/{id}/edit', name: 'app_admin_exercise_edit')]
    public function editExercise(
        int $id,
        ExerciseRepository $exerciseRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $exercise = $exerciseRepo->find($id);
        
        if (!$exercise) {
            $this->addFlash('error', 'Exercice introuvable.');
            return $this->redirectToRoute('app_admin_exercises');
        }

        return $this->render('admin/exercises/edit.html.twig', [
            'exercise' => $exercise,
        ]);
    }

    #[Route('/admin/exercises', name: 'app_admin_exercises')]
    public function exercises(
        ExerciseRepository $exerciseRepo,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST') && $request->request->get('action') === 'add') {
            $exercise = new Exercise();
            $exercise->setName($request->request->get('name'));
            $exercise->setDescription($request->request->get('description'));
            $exercise->setType($request->request->get('type'));
            $exercise->setLevel($request->request->get('level'));
            $exercise->setDuration((int) $request->request->get('duration'));
            $exercise->setCalories((int) $request->request->get('calories'));

            $em->persist($exercise);
            $em->flush();

            $this->addFlash('success', 'Exercice ajouté avec succès !');
            return $this->redirectToRoute('app_admin_exercises');
        }

        if ($request->isMethod('POST') && $request->request->get('action') === 'edit') {
            $id = $request->request->get('id');
            $exercise = $exerciseRepo->find($id);
            if ($exercise) {
                $exercise->setName($request->request->get('name'));
                $exercise->setDescription($request->request->get('description'));
                $exercise->setType($request->request->get('type'));
                $exercise->setLevel($request->request->get('level'));
                $exercise->setDuration((int) $request->request->get('duration'));
                $exercise->setCalories((int) $request->request->get('calories'));

                /** @var UploadedFile $imageFile */
                $imageFile = $request->files->get('image');
                if ($imageFile) {
                    $safeName = $slugger->slug(pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME));
                    // Utiliser pathinfo au lieu de guessExtension() pour éviter la dépendance à fileinfo
                    $extension = pathinfo($imageFile->getClientOriginalName(), PATHINFO_EXTENSION);
                    $imageFilename = $safeName.'-'.uniqid().'.'.$extension;

                    // Supprimer l'ancienne image si elle existe
                    if ($exercise->getImage()) {
                        $oldImagePath = $this->getParameter('kernel.project_dir') . '/public/images/' . $exercise->getImage();
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }

                    // Move file to public/images
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/images',
                        $imageFilename
                    );
                    $exercise->setImage($imageFilename);
                }

                $em->flush();
                $this->addFlash('success', 'Exercice modifié avec succès !');
            }
            return $this->redirectToRoute('app_admin_exercises');
        }

        if ($request->isMethod('POST') && $request->request->get('action') === 'delete') {
            $id = $request->request->get('id');
            $exercise = $exerciseRepo->find($id);
            if ($exercise) {
                $em->remove($exercise);
                $em->flush();
                $this->addFlash('success', 'Exercice supprimé avec succès !');
            }
            return $this->redirectToRoute('app_admin_exercises');
        }

        $exercises = $exerciseRepo->findAll();

        return $this->render('admin/exercises/exercises.html.twig', [
            'exercises' => $exercises,
        ]);
    }

    #[Route('/admin/foods/{id}/edit', name: 'app_admin_food_edit')]
    public function editFood(
        int $id,
        FoodRepository $foodRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $food = $foodRepo->find($id);
        
        if (!$food) {
            $this->addFlash('error', 'Aliment introuvable.');
            return $this->redirectToRoute('app_admin_foods');
        }

        return $this->render('admin/foods/edit.html.twig', [
            'food' => $food,
        ]);
    }

    #[Route('/admin/foods', name: 'app_admin_foods')]
    public function foods(
        FoodRepository $foodRepo,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST') && $request->request->get('action') === 'add') {
            $food = new Food();
            $food->setName($request->request->get('name'));
            $food->setCalories((float) $request->request->get('calories'));
            $food->setProtein((float) $request->request->get('protein'));
            $food->setCarbs((float) $request->request->get('carbs'));
            $food->setFat((float) $request->request->get('fat'));
            $food->setUnit($request->request->get('unit', 'g'));
            $food->setQuantity((float) $request->request->get('quantity', 100));

            $em->persist($food);
            $em->flush();

            $this->addFlash('success', 'Aliment ajouté avec succès !');
            return $this->redirectToRoute('app_admin_foods');
        }

        if ($request->isMethod('POST') && $request->request->get('action') === 'edit') {
            $id = $request->request->get('id');
            $food = $foodRepo->find($id);
            if ($food) {
                $food->setName($request->request->get('name'));
                $food->setCalories((float) $request->request->get('calories'));
                $food->setProtein((float) $request->request->get('protein'));
                $food->setCarbs((float) $request->request->get('carbs'));
                $food->setFat((float) $request->request->get('fat'));
                $food->setUnit($request->request->get('unit', 'g'));
                $food->setQuantity((float) $request->request->get('quantity', 100));
                $fiber = $request->request->get('fiber');
                $food->setFiber($fiber !== null && $fiber !== '' ? (float)$fiber : null);

                $em->flush();
                $this->addFlash('success', 'Aliment modifié avec succès !');
            }
            return $this->redirectToRoute('app_admin_foods');
        }

        if ($request->isMethod('POST') && $request->request->get('action') === 'delete') {
            $id = $request->request->get('id');
            $food = $foodRepo->find($id);
            if ($food) {
                $em->remove($food);
                $em->flush();
                $this->addFlash('success', 'Aliment supprimé avec succès !');
            }
            return $this->redirectToRoute('app_admin_foods');
        }

        $foods = $foodRepo->findAll();

        return $this->render('admin/foods/foods.html.twig', [
            'foods' => $foods,
        ]);
    }

    #[Route('/admin/bookings', name: 'app_admin_bookings')]
    public function bookings(
        BookingRepository $bookingRepo,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST') && $request->request->get('action') === 'update_status') {
            $id = $request->request->get('id');
            $status = $request->request->get('status');
            $booking = $bookingRepo->find($id);
            if ($booking && $status) {
                $booking->setStatus($status);
                $em->flush();
                $this->addFlash('success', 'Statut de la réservation mis à jour !');
            }
            return $this->redirectToRoute('app_admin_bookings');
        }

        $bookings = $bookingRepo->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/bookings.html.twig', [
            'bookings' => $bookings,
        ]);
    }

    #[Route('/admin/orders', name: 'app_admin_orders')]
    public function orders(
        OrderRepository $orderRepo,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST') && $request->request->get('action') === 'update_status') {
            $id = $request->request->get('id');
            $status = $request->request->get('status');
            $order = $orderRepo->find($id);
            if ($order && $status) {
                $order->setStatus($status);
                $em->flush();
                $this->addFlash('success', 'Statut de la commande mis à jour !');
            }
            return $this->redirectToRoute('app_admin_orders');
        }

        $orders = $orderRepo->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/orders.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/admin/stats', name: 'app_admin_stats')]
    public function stats(
        UserRepository $userRepo,
        ExerciseRepository $exerciseRepo,
        BookingRepository $bookingRepo,
        OrderRepository $orderRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $thisMonth = new \DateTime('first day of this month');
        
        $stats = [
            'totalUsers' => $userRepo->count([]),
            'newUsersThisMonth' => $userRepo->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u.createdAt >= :month')
                ->setParameter('month', $thisMonth)
                ->getQuery()
                ->getSingleScalarResult(),
            'totalExercises' => $exerciseRepo->count([]),
            'bookingsThisMonth' => $bookingRepo->createQueryBuilder('b')
                ->select('COUNT(b.id)')
                ->where('b.createdAt >= :month')
                ->setParameter('month', $thisMonth)
                ->getQuery()
                ->getSingleScalarResult(),
            'ordersThisMonth' => $orderRepo->createQueryBuilder('o')
                ->select('COUNT(o.id)')
                ->where('o.createdAt >= :month')
                ->setParameter('month', $thisMonth)
                ->getQuery()
                ->getSingleScalarResult(),
        ];

        return $this->render('admin/stats.html.twig', [
            'stats' => $stats,
        ]);
    }



    #[Route('admin/foods/add', name: 'app_food_add')]
public function addFood(Request $request, EntityManagerInterface $em, FoodRepository $foodRepo): Response
{
    $this->denyAccessUnlessGranted('ROLE_USER');

    if ($request->isMethod('POST')) {

        // Read form values
        $name = $request->request->get('name');
        $calories = $request->request->get('calories');
        $protein = $request->request->get('protein');
        $carbs = $request->request->get('carbs');
        $fat = $request->request->get('fat');
        $fiber = $request->request->get('fiber');
        $unit = $request->request->get('unit');
        $quantity = $request->request->get('quantity');

        // Basic validation
        if (!$name || !$calories || !$protein || !$carbs || !$fat) {
            $this->addFlash('error', 'Veuillez remplir tous les champs obligatoires.');
            return $this->redirectToRoute('app_food_add');
        }

        // Create new food entry
        $food = new Food();
        $food->setName($name);
        $food->setCalories((float)$calories);
        $food->setProtein((float)$protein);
        $food->setCarbs((float)$carbs);
        $food->setFat((float)$fat);
        $food->setFiber($fiber !== null && $fiber !== '' ? (float)$fiber : null);
        $food->setUnit($unit ?: null);
        $food->setQuantity($quantity !== null && $quantity !== '' ? (float)$quantity : null);

        // Persist and save
        $em->persist($food);
        $em->flush();

        $this->addFlash('success', 'Aliment ajouté avec succès !');

        return $this->redirectToRoute('app_admin_foods'); 
    }

     $foods = $foodRepo->findAll();

        return $this->render('admin/foods/add.html.twig', [
            'foods' => $foods,
        ]);
}


#[Route('/admin/exercises/add', name: 'app_exercise_add')]
public function addExercise(
    Request $request,
    EntityManagerInterface $em,
    SluggerInterface $slugger
): Response {
    $this->denyAccessUnlessGranted('ROLE_USER');

    if ($request->isMethod('POST')) {

        // Read text fields
        $name = $request->request->get('name');
        $description = $request->request->get('description');
        $type = $request->request->get('type');
        $level = $request->request->get('level');
        $duration = $request->request->get('duration');
        $calories = $request->request->get('calories');

        // Read the uploaded file
        /** @var UploadedFile $imageFile */
        $imageFile = $request->files->get('image');
        $imageFilename = null;
        if ($imageFile) {
            $safeName = $slugger->slug(pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME));
            // Utiliser pathinfo au lieu de guessExtension() pour éviter la dépendance à fileinfo
            $extension = pathinfo($imageFile->getClientOriginalName(), PATHINFO_EXTENSION);
            $imageFilename = $safeName.'-'.uniqid().'.'.$extension;

            // Move file to public/images
            $imageFile->move(
                $this->getParameter('kernel.project_dir') . '/public/images',
                $imageFilename
            );
        }

        $exercise = new Exercise();
        $exercise->setName($name);
        $exercise->setDescription($description ?: null);
        $exercise->setType($type);
        $exercise->setLevel($level);
        $exercise->setDuration($duration ? (int)$duration : null);
        $exercise->setCalories($calories ? (int)$calories : null);
        $exercise->setImage($imageFilename);

        $em->persist($exercise);
        $em->flush();

        $this->addFlash('success', 'Exercice ajouté avec succès !');
        return $this->redirectToRoute('app_admin_exercises');
    }

    return $this->render('admin/exercises/add.html.twig');
}

}


