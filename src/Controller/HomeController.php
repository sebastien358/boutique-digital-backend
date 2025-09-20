<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Service\ProductService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class HomeController extends AbstractController
{
    private $productRepository;
    private $productService;

    public function __construct(ProductRepository $productRepository, ProductService $productService
    ){
        $this->productRepository = $productRepository;
        $this->productService = $productService;
    }

    #[Route('/products', methods: ['GET'])]
    public function products(Request $request, NormalizerInterface $normalizer): JsonResponse
    {
        try {
            $offset = $request->query->getInt('offset', 0);
            $limit = $request->query->getInt('limit', 20);

            $products = $this->productRepository->findLoadProducts($offset, $limit);
            if (!$products) {
                return new JsonResponse(['message' => 'Products not found'], 404);
            }

            $dataProducts = $this->productService->getProductData($products, $request, $normalizer);
            return new JsonResponse($dataProducts);
        } catch(\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/products/search', methods: ['GET'])]
    public function searchProducts(Request $request, NormalizerInterface $normalizer): JsonResponse
    {
        try {
            $filterSearch = $request->query->getString('search');
            $products = $this->productRepository->findBySearch(['search' => $filterSearch]);

            $dataProducts = $this->productService->getProductData($products, $request, $normalizer);
            return new JsonResponse($dataProducts);
        } catch(\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/products/filtered/price', methods: ['GET'])]
    public function filteredPrice(Request $request, NormalizerInterface $normalizer): JsonResponse
    {
        try {
            $minPrice = $request->query->getInt('minPrice');
            $maxPrice = $request->query->getInt('maxPrice');
            $products = $this->productRepository->findByPrice($minPrice, $maxPrice);

            $dataProducts = $this->productService->getProductData($products, $request, $normalizer);
            return new JsonResponse($dataProducts);
        } catch(\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/products/filtered/category', methods: ['GET'])]
    public function filteredCategory(Request $request, NormalizerInterface $normalizer): JsonResponse
    {
        try {
            $category = $request->query->getString('category');
            $products = $this->productRepository->findByCategory($category);

            $dataProducts = $this->productService->getProductData($products, $request, $normalizer);
            return new JsonResponse($dataProducts);
        } catch(\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
