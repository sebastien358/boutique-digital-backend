<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Repository\CartItemRepository;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/api/item')]
#[IsGranted('ROLE_USER')]
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

    #[Route('/list', methods: ['GET'])]
    public function items(NormalizerInterface $normalizer, CartRepository $cartRepository): JsonResponse
    {
        try {
            $user = $this->getUser();
            $cart = $cartRepository->findOneBy(['user' => $user]);
            if (!$cart) {
                return new JsonResponse([], 200);
            }
            $cartItems = $cart->getItems();
            $dataCarts = $normalizer->normalize($cartItems, 'json', [
                'groups' => ['carts'],
                'circular_reference_handler' => function ($object) {
                    return $object->getId();
                }
            ]);
            return new JsonResponse($dataCarts);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/new', methods: ['POST'])]
    public function new(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $user = $this->getUser();

            $cart = $this->entityManager->getRepository(Cart::class)->findOneBy(['user' => $user]);
            if (!$cart) {
                $cart = new Cart();
                $cart->setUser($user);
                $this->entityManager->persist($cart);
            }

            foreach ($data as $item) {
                $existingCartItem = $this->cartItemRepository->findOneBy(['cart' => $cart, 'productId' => $item['id']]);
                if ($existingCartItem) {
                    $existingCartItem->setQuantity($existingCartItem->getQuantity() + $item['quantity']);
                    $this->entityManager->persist($existingCartItem);
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


    #[Route('/delete/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
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

