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

final class CartController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/api/cart', methods: ['POST'])]
    public function addToCart(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $session = $request->getSession();
        $cartId = $session->get('cart_id');
        if ($cartId) {
            $cart = $this->entityManager->getRepository(Cart::class)->find($cartId);
        } else {
            $cart = new Cart();
            $this->entityManager->persist($cart);
            $this->entityManager->flush();
            $session->set('cart_id', $cart->getId());
        }

        foreach ($data as $item) {
            $existingCartItem = $this->entityManager->getRepository(CartItem::class)->findOneBy(['productId' => $item['id']]);
            if ($existingCartItem) {
                if ($existingCartItem->getCart() !== $cart) {
                    $existingCartItem->setCart($cart);
                }
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

        return new JsonResponse('Données enregistrées avec succès', 201);
    }

}

