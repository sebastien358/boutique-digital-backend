<?php 

namespace App\Controller\Admin;

use App\Entity\Picture;
use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Service\fileUploader;
use App\Service\ProductService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Test\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[IsGranted('ROLE_USER')]
#[Route('/admin')]
final class ProductAdminController extends AbstractController
{
  public function __construct(
    private ProductRepository $productRepository,
    private EntityManagerInterface $entityManager,
    private ProductService $productService,
    private fileUploader $fileUploader
  ){ 
  }

  #[Route('/products', methods: ['GET'])]
  function products(Request $request, NormalizerInterface $normalizer): JsonResponse
  {
    try {
      $page = $request->query->getInt('page', 1);
      $limit = $request->query->getInt('limit', 20);
      $products = $this->productRepository->findAllProducts($page, $limit);
      $total = $this->productRepository->countAllProducts();
      if (!$products) {
        return new JsonResponse(['message' => 'Les produits sont introuvables']);
      }
      $dataProducts = $this->productService->getProductData($products, $request, $normalizer);
      return new JsonResponse([
        'products' => $dataProducts,
        'total' => $total
      ]);
    } catch(\Exception $e) {
      return new JsonResponse(['error' => $e->getMessage()], 500);
    }
  }

  #[Route('/product/delete/{id}', methods: ['DELETE'])]
  function delete(int $id): JsonResponse
  {
    try {
      $product = $this->productRepository->find($id);
      if (!$product) {
        return new JsonResponse(['message' => 'Produit introuvable']);
      }
      foreach ($product->getPictures() as $picture) {
        $fileName = $this->getParameter('images_directory') . '/' . $picture->getFilename();
        if (file_exists($fileName)) {
          unlink($fileName);
        }
        $this->entityManager->remove($picture);
      }
      $this->entityManager->remove($product);
      $this->entityManager->flush();
      return new JsonResponse(['message' => 'Le produit a bien été supprimé'], 200);
    } catch(\Exception $e) {
      return new JsonResponse(['error' => $e->getMessage()], 500);
    }
  }

  #[Route('/product/new')]
  public function newProduct(Request $request): JsonResponse
  {
    try {
      $product = new Product();
      $form = $this->createForm(ProductType::class, $product);
      $form->submit($request->request->all());
      if ($form->isValid() && $form->isSubmitted()) {
        $category = $form->get('category')->getData();
        $product->setCategory($category);
        $this->entityManager->persist($product);
        $images = $request->files->get('filename', []);
        if (!empty($images)) {
          foreach ($images as $image) {
            $newFilename = $this->fileUploader->upload($image);
            $picture = new Picture();
            $picture->setFilename($newFilename);
            $picture->setProduct($product);
            $this->entityManager->persist($picture);
          }
        } else {
          return new JsonResponse($this->getErrorMessages($form), 400);
        }
        $this->entityManager->flush();
        return new JsonResponse(['message' => 'Produit ajouté avec succès'], 201);
      }
    } catch(\Exception $e) {
      return new JsonResponse(['error' => $e->getMessage()], 500);
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