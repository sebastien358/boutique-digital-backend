<?php

namespace App\Controller;

use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use App\Entity\Product;
use App\Service\ProductService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    private $entityManager;
    private $productService;
    private $logger;

    public function __construct(
        EntityManagerInterface $entityManager, ProductService $productService, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->productService = $productService;
        $this->logger = $logger;
    }

    #[Route('/products', methods: ['GET'])]
    public function products(Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $offset = $request->query->getInt('offset', 0);
            $limit = $request->query->getInt('limit', 20);
            $products = $this->entityManager->getRepository(Product::class)->findLoadProducts($offset, $limit);

            $dataProducts = $this->productService->getProductData($products, $request, $serializer);
            return new JsonResponse($dataProducts);
        } catch(Throwable $e) {
            $this->logger->error('Erreur de la récupération des produits : ', ['message' => $e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/products/search', methods: ['GET'])]
    public function searchProducts(Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $search = $request->query->getString('search');
            $products = $this->entityManager->getRepository(Product::class)->findBySearch($search);

            $dataProducts = $this->productService->getProductData($products, $request, $serializer);

            return new JsonResponse($dataProducts, 200);
        } catch(Throwable $e) {
            $this->logger->error('Erreur de la récupération des produits "search" : ', ['message' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Erreur interne du server'], 500);
        }
    }

    #[Route('/products/filtered/price', methods: ['GET'])]
    public function filteredPrice(Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $minPrice = $request->query->getInt('minPrice');
            $maxPrice = $request->query->getInt('maxPrice');
            $products = $this->entityManager->getRepository(Product::class)->findByPrice($minPrice, $maxPrice);

            $dataProducts = $this->productService->getProductData($products, $request, $serializer);

            return new JsonResponse($dataProducts, 200);
        } catch(Throwable $e) {
            $this->logger->error('Erreur de la récupération des produits par "price" : ', ['message' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Erreur interne du server'], 500);
        }
    }

    #[Route('/products/filtered/category', methods: ['GET'])]
    public function filteredCategory(Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $category = $request->query->getString('category');
            $products = $this->entityManager->getRepository(Product::class)->findByCategory($category);

            $dataProducts = $this->productService->getProductData($products, $request, $serializer);

            return new JsonResponse($dataProducts, 200);
        } catch(Throwable $e) {
            $this->logger->error('Erreur de la récupération des produits par "catégorie" : ', ['message' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Erreur interne du server'], 500);
        }
    }
}
