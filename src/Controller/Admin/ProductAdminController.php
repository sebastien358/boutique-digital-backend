<?php

namespace App\Controller\Admin;

use App\Entity\Picture;
use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\PictureRepository;
use App\Repository\ProductRepository;
use App\Service\fileUploader;
use App\Service\ProductService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Doctrine\DBAL\Exception\DBALException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Test\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
final class ProductAdminController extends AbstractController
{
    private $productRepository;
    private $entityManager;
    private  $productService;
    private $fileUploader;
    private $logger;

  public function __construct(
      ProductRepository $productRepository, EntityManagerInterface $entityManager, ProductService $productService,
      fileUploader $fileUploader, LoggerInterface $logger
  ){
      $this->productRepository = $productRepository;
      $this->entityManager = $entityManager;
      $this->productService = $productService;
      $this->fileUploader = $fileUploader;
      $this->logger = $logger;
  }

  #[Route('/products', methods: ['GET'])]
  function products(Request $request, NormalizerInterface $normalizer): JsonResponse
  {
    try {
      $page = $request->query->getInt('page', 1);
      $limit = $request->query->getInt('limit', 20);

      $products = $this->productRepository->findAllProducts($page, $limit);

      if (!$products) {
          return new JsonResponse(['message' => 'Les produits sont introuvables']);
      }

      $total = $this->productRepository->countAllProducts();
      $dataProducts = $this->productService->getProductData($products, $request, $normalizer);

      return new JsonResponse([
        'products' => $dataProducts,
        'total' => $total
      ]);

    } catch(\Throwable $e) {
        $this->logger->error('Erreur de la récupération des produits', [$e->getMessage()]);
        return new JsonResponse(['error' => 'Erreur interne du serveur'], 500);
    }
  }

  #[Route('/product/{id}', methods: ['GET'])]
  function product (int $id, Request $request, NormalizerInterface $normalizer): JsonResponse
  {
    try {
      $products = $this->productRepository->find($id);

      if (!$products) {
        return new JsonResponse(['message' => 'Le produit est introuvable']);
      }

      $dataProduct = $this->productService->getProductData($products, $request, $normalizer);
      return new JsonResponse($dataProduct);

    } catch(\Throwable $e) {
        $this->logger->error('Erreur de la récupération d\'un produit', [$e->getMessage()]);
        return new JsonResponse(['error' => 'Erreur interne du serveur'], 500);
    }
  }

  #[Route('/product/new', methods: ['POST'])]
  public function newProduct(Request $request): JsonResponse
  {
    try {
      $product = new Product();

      $form = $this->createForm(ProductType::class, $product);
      $form->submit($request->request->all());

      if ($form->isValid() && $form->isSubmitted()) {
        $category = $form->get('category')->getData();
        $product->setCategory($category);

        $images = $request->files->get('filename', []);
        if (!empty($images)) {
            foreach ($images as $image) {
                $newFilename = $this->fileUploader->upload($image);
                $picture = new Picture();
                $picture->setFilename($newFilename);
                $picture->setProduct($product);
                $this->entityManager->persist($picture);
            }
        }

        $this->entityManager->persist($product);
        try {
            $this->entityManager->flush();
        } catch(DBALException $e) {
            $this->logger->error('Le produit n\'a pas pu être ajouté' . $e->getMessage());
            return new JsonResponse(['error' => 'Erreur interne'], 500);
        }

        return new JsonResponse(['message' => 'Produit ajouté avec succès'], 201);
      } else {
          $errors = $this->getErrorMessages($form);
          return new JsonResponse(['errors' => $errors], 400);
      }
    } catch(\Throwable $e) {
      $this->logger->error('Le produit n\'a pas pu être ajouté' . $e->getMessage());
      return new JsonResponse(['error' => 'Erreur interne'], 500);
    }
  }

  #[Route('/product/edit/{id}', methods: ['POST'])]
  public function edit(int $id, Request $request): JsonResponse
  {
    try {
      $product = $this->productRepository->find($id);

      $form = $this->createForm(ProductType::class, $product);
      $form->submit($request->request->all());

      if ($form->isValid() && $form->isSubmitted()) {
        $category = $form->get('category')->getData();
        $product->setCategory($category);

        $images = $request->files->get('filename', []);
        if (!empty($images)) {
          foreach ($images as $image) {
            $newFilename = $this->fileUploader->upload($image);

            $picture = new Picture();
            $picture->setFilename($newFilename);
            $product->addPicture($picture);
            $this->entityManager->persist($picture);
          }
        }

        try {
            $this->entityManager->flush();
        } catch(\DBALException $e) {
            $this->logger->error('Erreur de la modification du produit' . $e->getMessage());
            return new JsonResponse(['error' => 'Erreur interne'], 500);
        }

        return new JsonResponse(['message' => 'Produit modifié avec succès'], 201);
      } else {
          $errors = $this->getErrorMessages($form);
          return new JsonResponse(['errors' => $errors], 400);
      }
    } catch(\Throwable $e) {
        $this->logger->error('Erreur de la modification du produit' . $e->getMessage());
        return new JsonResponse(['error' => 'Erreur interne'], 500);
    }
  }

  #[Route('/product/delete/{id}', methods: ['DELETE'])]
  function delete(int $id): JsonResponse
  {
    try {
      $product = $this->productRepository->find($id);
      if (!$product) {
        return new JsonResponse(['message' => 'Produit introuvable'], 404);
      }

      foreach ($product->getPictures() as $picture) {
        $fileName = $this->getParameter('images_directory') . '/' . $picture->getFilename();
        if (file_exists($fileName)) {
          unlink($fileName);
        }
        $this->entityManager->remove($picture);
      }

      $this->entityManager->remove($product);

      try {
          $this->entityManager->flush();
      } catch(DBALException $e) {
          $this->logger->error('Erreur de la suppression du produit' . $e->getMessage());
          return new JsonResponse(['error' => 'Erreur interne'], 500);
      }

      return new JsonResponse(['message' => 'Le produit a bien été supprimé'], 200);
    } catch(\Throwable $e) {
        $this->logger->error('Erreur de la suppression d\'un produit', ['message' => $e->getMessage()]);
        return new JsonResponse(['error' => 'Erreur interne du serveur'], 500);
    }
  }

  #[Route('/product/{productId}/picture/{pictureId}', methods: ['DELETE'])]
  function deletePicture(int $productId, int $pictureId, PictureRepository $pictureRepository): JsonResponse
  {
    try {
      $product = $this->productRepository->find($productId);
      if (!$product) {
        return new JsonResponse(['message' => 'Produit introuvable'], 404);
      }

      $picture = $pictureRepository->find($pictureId);
      if (!$picture) {
        return new JsonResponse(['message' => 'Image introuvable'], 404);
      }

      if ($picture->getProduct()->getId() !== $productId) {
        return new JsonResponse(['message' => 'L\'image ne correspond pas au produit'], 404);
      }

      $filename = $this->getParameter('images_directory') . '/' . $picture->getFilename();
      if (file_exists($filename)) {
        unlink($filename);
      }

      $product->removePicture($picture);
      $this->entityManager->remove($picture);

      try {
        $this->entityManager->flush();
      } catch(DBALException $e) {
        $this->logger->error('Erreur de la suppression des images du produit', ['message' => $e->getMessage()]);
      return new JsonResponse(['error' => 'Erreur interne du serveur'], 500);
      }

      return new JsonResponse(['message' => 'L\'image a bien été supprimée'], 200);
    } catch(\Throwable $e) {
        $this->logger->error('Erreur de la suppression des images du produit', ['message' => $e->getMessage()]);
        return new JsonResponse(['error' => 'Erreur interne du serveur'], 500);
    }
  }

  private function getErrorMessages(FormInterface $form): array
  {
    $errors = [];
    foreach ($form->getErrors(true) as $error) {
      $errors[] = $error->getMessage();
      error_log($error->getMessage());
    }
    error_log('Nombre d\'erreurs : ' . count($errors));
    return $errors;
  }
}

