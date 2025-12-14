<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    #[Route('/products', name: 'app_products')]
    public function index(ProductRepository $productRepo, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $category = $request->query->get('category');
        
        $queryBuilder = $productRepo->createQueryBuilder('p')
            ->where('p.available = true')
            ->orderBy('p.createdAt', 'DESC');
        
        if ($category) {
            $queryBuilder->andWhere('p.category = :category')
                ->setParameter('category', $category);
        }
        
        $products = $queryBuilder->getQuery()->getResult();
        
        // Get unique categories
        $categories = $productRepo->createQueryBuilder('p')
            ->select('DISTINCT p.category')
            ->where('p.available = true')
            ->getQuery()
            ->getResult();
        
        $categoryList = array_column($categories, 'category');

        return $this->render('product/index.html.twig', [
            'products' => $products,
            'categories' => $categoryList,
            'selectedCategory' => $category,
        ]);
    }

    #[Route('/products/{id}/order', name: 'app_product_order', methods: ['POST'])]
    public function order(
        int $id,
        ProductRepository $productRepo,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $product = $productRepo->find($id);
        
        if (!$product || !$product->isAvailable()) {
            $this->addFlash('error', 'Produit introuvable ou non disponible.');
            return $this->redirectToRoute('app_products');
        }

        $quantity = (int) $request->request->get('quantity', 1);
        
        if ($quantity < 1) {
            $this->addFlash('error', 'La quantité doit être au moins 1.');
            return $this->redirectToRoute('app_products');
        }

        $order = new Order();
        $order->setUser($this->getUser());
        $order->setProductName($product->getName());
        $order->setQuantity($quantity);
        $totalPrice = (float) $product->getPrice() * $quantity;
        $order->setPrice(number_format($totalPrice, 2, '.', ''));
        $order->setStatus('pending');

        $em->persist($order);
        $em->flush();

        $this->addFlash('success', 'Commande créée avec succès ! Elle sera traitée par l\'administrateur.');
        return $this->redirectToRoute('app_orders');
    }
}

