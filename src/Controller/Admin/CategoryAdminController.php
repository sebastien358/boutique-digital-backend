<?php

namespace App\Controller\Admin;

use App\Repository\CategoryRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
final class CategoryAdminController extends AbstractController
{
    private $categoryRepository;
    private $logger;

    public function __construct(CategoryRepository $categoryRepository, LoggerInterface $logger)
    {
        $this->categoryRepository = $categoryRepository;
        $this->logger = $logger;
    }

  #[Route('/categories', methods: ['GET'])]
  public function category(NormalizerInterface $normalizer): JsonResponse
  {
    try {
      $categories = $this->categoryRepository->findAll();
      if (!$categories) {
        return new JsonResponse(['message' => 'Catégories introuvales'], 404);
      }
      $dataCategories = $normalizer->normalize($categories, 'json', ['groups' => 'products']);
      return new JsonResponse($dataCategories, 200);
    } catch(\Throwable $e) {
        $this->logger->error('Erreur de la récupération des catégories', [$e->getMessage()]);
        return new JsonResponse(['error' => 'Erreur interne du serveur'], 500);
    }
  }
}
