<?php

namespace App\Controller;

use Throwable;
use Exception;
use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/item')]
#[IsGranted('ROLE_USER')]
final class CartController extends AbstractController
{
    private $entityManager;
    private $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    #[Route('/list', methods: ['GET'])]
    public function carts(SerializerInterface $serializer): JsonResponse
    {
        try {
            $user = $this->getUser();

            $cart = $this->entityManager->getRepository(Cart::class)->findOneBy(['user' => $user]);
            if (!$cart) {
                return new JsonResponse(['error' => 'No carts found'], 404);
            }

            $items = $cart->getItems();
            $dataItems = $serializer->normalize($items, 'json', ['groups' => ['carts', 'cart-items'],
                'circular_reference_handler' => function ($object) {
                    return $object->getId();
                }
            ]);

            return new JsonResponse($dataItems, 200);
        } catch(Exception $e) {
            $this->logger->error('error de la récupération du panier de l\'utilisateur', [$e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/new', methods: ['POST'])]
    public function new(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

            $user = $this->getUser();

            $cart = $this->entityManager->getRepository(Cart::class)->findOneBy(['user' => $user]);
            if (!$cart) {
                $cart = new Cart();
                $user->setCart($cart);
                $this->entityManager->persist($user);
            }

            foreach ($data as $item) {
                $product = $this->entityManager->getRepository(Product::class)->findOneBy(['id' => $item['id']]);
                if (!$product) {
                    return new JsonResponse(['message' => 'Product not found'], 404);
                }

                if ($item['quantity'] <= 0) {
                    return new JsonResponse(['message' => 'Quantity invalid'], 400);
                }

                $cartItemExisting = $this->entityManager->getRepository(CartItem::class)->findOneBy(['cart' => $cart, 'product' => $product]);

                if ($cartItemExisting) {
                    $cartItemExisting->setQuantity($cartItemExisting->getQuantity() + $item['quantity']);
                    $this->entityManager->persist($cartItemExisting);
                } else {
                    $cartItem = new CartItem();
                    $cartItem->setCart($cart);
                    $cartItem->setProduct($product);
                    $cartItem->setTitle($product->getTitle());
                    $cartItem->setPrice($product->getPrice());
                    $cartItem->setQuantity($item['quantity']);
                    $this->entityManager->persist($cartItem);
                }
            }

            try {
                $this->entityManager->flush();
            } catch (Exception $e) {
                $this->logger->error('Error new item to cart', [$e->getMessage()]);
                return new JsonResponse(['error' => $e->getMessage()], 500);
            }

            return new JsonResponse(['success' => true, 'message' => 'Add new item to cart'],  200);
        } catch(Throwable $e) {
            $this->logger->error('Error new item to cart', ['message' => $e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/delete/{id}', methods: ['DELETE'])]
    public function deleteItemExisting(int $id): JsonResponse
    {
        try {
            $user = $this->getUser();

            $cart = $this->entityManager->getRepository(Cart::class)->findOneBy(['user' => $user]);
            if (!$cart) {
                return new JsonResponse(['error' => 'Error new item to cart'], 404);
            }

            $itemToCartExisting = $this->entityManager->getRepository(CartItem::class)->findOneBy(['cart' => $cart, 'id' => $id]);
            if (!$itemToCartExisting) {
                return new JsonResponse(['error' => 'Item not found'], 404);
            }

            if ($itemToCartExisting->getQuantity() > 1) {
                $itemToCartExisting->setQuantity($itemToCartExisting->getQuantity() - 1);
                $this->entityManager->persist($itemToCartExisting);
            } else {
                $this->entityManager->remove($itemToCartExisting);
            }

            try {
                $this->entityManager->flush();
            } catch(Exception $e) {
                $this->logger->error('Error delete items to cart', [$e->getMessage()]);
                return new JsonResponse(['error' => $e->getMessage()], 500);
            }

            return new JsonResponse(['success' => true, 'message' => 'Delete items to cart'], 200);
        } catch (Throwable $e) {
            $this->logger->error('Error delete items to cart', [$e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/add/{id}', methods: ['POST'])]
    public function addItemExisting(int $id): JsonResponse
    {
        try {
            $user = $this->getUser();

            $cart = $this->entityManager->getRepository(Cart::class)->findOneBy(['user' => $user]);
            if (!$cart) {
                return new JsonResponse(['error' => 'No cart found'], 404);
            }

            $itemToCartExisting = $this->entityManager->getRepository(CartItem::class)->findOneBy(['cart' => $cart, 'id' => $id]);
            if (!$itemToCartExisting) {
                return new JsonResponse(['error' => 'Item not found'], 404);
            }

            $itemToCartExisting->setQuantity($itemToCartExisting->getQuantity() + 1);
            $this->entityManager->persist($itemToCartExisting);

            try {
                $this->entityManager->flush();
            } catch(Exception $e) {
                $this->logger->error('Error add cart', [$e->getMessage()]);
                return new JsonResponse(['error' => $e->getMessage()], 500);
            }
            return new JsonResponse(['success' => true, 'message' => 'Incremented quantity'],  200);
        } catch (Throwable $e) {
            $this->logger->error('Error add item existing', [$e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}

