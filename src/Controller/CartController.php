<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Repository\CartItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Normalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class CartController extends AbstractController
{
    private $entityManager;
    private $cartItemRepository;

    public function __construct(EntityManagerInterface $entityManager, CartItemRepository $cartItemRepository)
    {
        $this->entityManager = $entityManager;
        $this->cartItemRepository = $cartItemRepository;
    }

    #[Route('/api/carts', methods: ['GET'])]
    public function items(NormalizerInterface $normalizer): JsonResponse
    {
        try {
            $carts = $this->cartItemRepository->findAll();
            if (!$carts) {
                return new JsonResponse(['message' => 'Éléments du panier introuvables'], 404);
            }
            $dataCarts = $normalizer->normalize($carts, 'json', ['groups' => 'carts']);
            return new JsonResponse($dataCarts);
        } catch(\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }


    #[Route('/api/cart/new', methods: ['POST'])]
    public function addToCart(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $cart = new Cart();
        $this->entityManager->persist($cart);

        foreach ($data as $item) {
            $itemExisting = $this->entityManager->getRepository(CartItem::class)->findOneBy(['productId' => $item['id']]);
            if ($itemExisting) {
                if ($itemExisting->getCart() !== $cart) {
                    $itemExisting->setCart($cart);
                }
                $itemExisting->setQuantity($itemExisting->getQuantity() + $item['quantity']);
                $this->entityManager->persist($itemExisting);
            } else {
                $cartItem = new CartItem();
                $cartItem->setCart($cart);
                $cartItem->setProductId($item['id']);
                $cartItem->setTitle($item['title']);
                $cartItem->setPrice($item['price']);
                $cartItem->setQuantity($item['quantity']);
                $this->entityManager->persist($cartItem);
            }

            $this->entityManager->flush();
        }

        return new JsonResponse('Données enregistrées avec succès', 201);
    }

    #[Route('/api/cart/delete/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $itemExisting = $this->cartItemRepository->findOneBy(['id' => $id]);
            if (!$itemExisting) {
                return new JsonResponse(['message', 'Produit est inexistant'], 404);
            } elseif($itemExisting->getQuantity() > 1) {
                 $itemExisting->setQuantity($itemExisting->getQuantity() - 1);
                $this->entityManager->persist($itemExisting);
            } else {
                $this->entityManager->remove($itemExisting);
            }
            $this->entityManager->flush();
            return new JsonResponse(['message', 'Le produit a été supprimé du panier avec succès'], 200);
        } catch(\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

}

