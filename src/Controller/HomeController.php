<?php

namespace App\Controller;

use Throwable;
use App\Entity\Product;
use App\Service\ProductService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class HomeController extends AbstractController
{
    private $productService;
    private $entityManager;
    private $logger;

    public function __construct(
        EntityManagerInterface $entityManager, ProductService $productService, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->productService = $productService;
        $this->logger = $logger;
    }

    #[Route('/products', methods: ['GET'])]
    public function products(Request $request, NormalizerInterface $normalizer): JsonResponse
    {
        try {
            $offset = $request->query->getInt('offset', 0);
            $limit = $request->query->getInt('limit', 20);

            $products = $this->entityManager->getRepository(Product::class)->findLoadProducts($offset, $limit);
            if (!$products) {
                return new JsonResponse(['message' => 'Products not found'], 404);
            }
            $dataProducts = $this->productService->getProductData($products, $request, $normalizer);
            return new JsonResponse($dataProducts, 200);
        } catch(Throwable $e) {
            $this->logger->error('Erreur de la récupération des produits : ', ['message' => $e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/products/search', methods: ['GET'])]
    public function searchProducts(Request $request, NormalizerInterface $normalizer): JsonResponse
    {
        try {
            $filterSearch = $request->query->getString('search');
            $products = $this->entityManager->getRepository(Product::class)->findBySearch(['search' => $filterSearch]);

            $dataProducts = $this->productService->getProductData($products, $request, $normalizer);

            return new JsonResponse($dataProducts, 200);
        } catch(Throwable $e) {
            $this->logger->error('Erreur de la récupération des produits "search" : ', ['message' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Erreur interne du server'], 500);
        }
    }

    #[Route('/products/filtered/price', methods: ['GET'])]
    public function filteredPrice(Request $request, NormalizerInterface $normalizer): JsonResponse
    {
        try {
            $minPrice = $request->query->getInt('minPrice');
            $maxPrice = $request->query->getInt('maxPrice');
            $products = $this->entityManager->getRepository(Product::class)->findByPrice($minPrice, $maxPrice);

            $dataProducts = $this->productService->getProductData($products, $request, $normalizer);

            return new JsonResponse($dataProducts, 200);
        } catch(Throwable $e) {
            $this->logger->error('Erreur de la récupération des produits par "price" : ', ['message' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Erreur interne du server'], 500);
        }
    }

    #[Route('/products/filtered/category', methods: ['GET'])]
    public function filteredCategory(Request $request, NormalizerInterface $normalizer): JsonResponse
    {
        try {
            $category = $request->query->getString('category');
            $products = $this->entityManager->getRepository(Product::class)->findByCategory($category);

            $dataProducts = $this->productService->getProductData($products, $request, $normalizer);

            return new JsonResponse($dataProducts, 200);
        } catch(Throwable $e) {
            $this->logger->error('Erreur de la récupération des produits par "catégorie" : ', ['message' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Erreur interne du server'], 500);
        }
    }
}
