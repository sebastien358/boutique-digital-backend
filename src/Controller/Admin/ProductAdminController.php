<?php 

namespace App\Controller\Admin;

use App\Entity\Picture;
use App\Entity\Product;
use App\Form\ProductType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Test\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/admin')]
final class ProductAdminController extends AbstractController
{
  #[Route('/product/new')]
  public function newProduct(Request $request, EntityManagerInterface $entityManager): JsonResponse
  {
    try {
      $product = new Product();
      $form = $this->createForm(ProductType::class, $product);
      $form->submit($request->request->all());
      if ($form->isValid() && $form->isSubmitted()) {
        $category = $form->get('category')->getData();
        $product->setCategory($category);
        $entityManager->persist($product);
        $images = $request->files->get('filename');
        if (!empty($images)) {
          foreach ($images as $image) {
            $newFilename = uniqid().'.'.$image->getExtension();
            $image->move($this->getParameter('images_directory'), $newFilename);
            $picture = new Picture();
            $picture->setFilename($newFilename);
            $picture->setProduct($product);
            $entityManager->persist($picture);
          }
        } else {
          return new JsonResponse($this->getErrorMessages($form), 400);
        }
        $entityManager->flush();
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