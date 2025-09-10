<?php 

namespace App\Controller\Admin;

use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/admin')]
final class CategoryAdminController extends AbstractController
{
  #[Route('/categories', methods: ['GET'])]
  public function category(CategoryRepository $categoryRepository, NormalizerInterface $normalizer): JsonResponse
  { 
    try {
      $categories = $categoryRepository->findAll();
      if (!$categories) {
        return new JsonResponse(['message' => 'Catégories introuvales'], 404);
      }
      $dataCategories = $normalizer->normalize($categories, 'json', ['groups' => 'products']);
      return new JsonResponse($dataCategories);
    } catch(\Exception $e) {
      return new JsonResponse(['error' => $e->getMessage()], 500);
    }
  } 
}
