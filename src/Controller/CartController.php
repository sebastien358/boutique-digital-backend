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
    public function new(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $user = $this->getUser();

            $cart = new Cart();
            $cart->setUser($user);
            $this->entityManager->persist($cart);

            foreach ($data as $item) {
                $itemExistingCart = $this->cartItemRepository->findOneBy(['productId' => $item['id']]);
                if ($itemExistingCart && $itemExistingCart->getCart()->getId() !== $cart->getId()) {
                    $itemExistingCart->setCart($cart);
                    $itemExistingCart->setQuantity($itemExistingCart->getQuantity() + $item['quantity']);
                    $this->entityManager->persist($itemExistingCart);
                } else {
                    $cartItem = new CartItem();
                    $cartItem->setCart($cart);
                    $cartItem->setProductId($item['id']);
                    $cartItem->setTitle($item['title']);
                    $cartItem->setPrice($item['price']);
                    $cartItem->setQuantity($item['quantity']);
                    $this->entityManager->persist($cartItem);
                }
            }
            $this->entityManager->flush();

            return new JsonResponse(['success' => true, 'message' => 'Item added to cart']);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/delete/item/{id}', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        try {
            $itemExisting = $this->entityManager->getRepository(CartItem::class)->findOneBy(['id' => $id]);

            if ($itemExisting && $itemExisting->getQuantity() > 1) {
                $itemExisting->setQuantity($itemExisting->getQuantity() - 1);

                $this->entityManager->persist($itemExisting);
            } else {
                $this->entityManager->remove($itemExisting);
            }
            $this->entityManager->flush();

            return new JsonResponse(['success' => true, 'message' => 'Item deleted from cart'], 200);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

}

