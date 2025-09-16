<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Repository\CartItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class CartController extends AbstractController
{
    private $entityManager;
    private $cartItemRepository;

    public function __construct(EntityManagerInterface $entityManager, CartItemRepository $cartItemRepository
    )
    {
        $this->entityManager = $entityManager;
        $this->cartItemRepository = $cartItemRepository;
    }

    #[Route('/api/items', methods: ['GET'])]
    public function items(NormalizerInterface $normalizer): JsonResponse
    {
        try {
            $carts = $this->cartItemRepository->findAll();
            $dataCarts = $normalizer->normalize($carts, 'json', ['groups' => 'carts']);
            return new JsonResponse($dataCarts);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/item/new', methods: ['POST'])]
    public function addToCart(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $cart = new Cart();
            $this->entityManager->persist($cart);

            foreach ($data as $item) {
                $itemExist = $this->cartItemRepository->findOneBy(['productId' => $item['id']]);
                if ($itemExist) {
                    if ($itemExist->getCart() !== $cart) {
                        $itemExist->setCart($cart);
                    }
                    $itemExist->setQuantity($itemExist->getQuantity() + $item['quantity']);
                    $this->entityManager->persist($itemExist);
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
            return new JsonResponse(['success' => true, 'message' => 'Item added to cart successfully.'], 201);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/delete/item/{id}', methods: ['DELETE'])]
    public function removeItem(int $id): JsonResponse
    {
        try {
            $item = $this->cartItemRepository->findOneBy(['id' => $id]);

            if ($item && $item->getQuantity() > 1) {
                $item->setQuantity($item->getQuantity() - 1);
            } else {
                $this->entityManager->remove($item);
            }

            $this->entityManager->flush();
            return new JsonResponse(['success' => true, 'message' => 'Item removed from cart successfully.'], 201);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}

