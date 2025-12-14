<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OrderController extends AbstractController
{
    #[Route('/orders/create', name: 'app_order_create')]
    public function create(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($request->isMethod('POST')) {
            $productName = $request->request->get('product_name');
            $quantity = (int) $request->request->get('quantity', 1);
            $price = $request->request->get('price');

            if ($productName && $price) {
                $order = new Order();
                $order->setUser($this->getUser());
                $order->setProductName($productName);
                $order->setQuantity($quantity);
                $order->setPrice($price);
                $order->setStatus('pending');

                $em->persist($order);
                $em->flush();

                $this->addFlash('success', 'Commande créée avec succès !');
                return $this->redirectToRoute('app_dashboard');
            }
        }

        return $this->render('order/create.html.twig');
    }

    #[Route('/orders', name: 'app_orders')]
    public function index(OrderRepository $orderRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        
        $orders = $orderRepo->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('order/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/orders/{id}/delete', name: 'app_order_delete', methods: ['POST'])]
    public function delete(
        int $id,
        OrderRepository $orderRepo,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $order = $orderRepo->find($id);
        
        if (!$order || $order->getUser() !== $this->getUser()) {
            $this->addFlash('error', 'Commande introuvable.');
            return $this->redirectToRoute('app_orders');
        }

        $em->remove($order);
        $em->flush();

        $this->addFlash('success', 'Commande supprimée avec succès !');
        return $this->redirectToRoute('app_orders');
    }
}

