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
    public function cartItems(SerializerInterface $serializer): JsonResponse
    {
        try {
            $user = $this->getUser();

            $cart = $this->entityManager->getRepository(Cart::class)->findOneBy(['user' => $user]);
            if (!$cart) {
                return new JsonResponse(['message' => 'Le panier n\'existe pas'], 404);
            }

            $items = $cart->getItems();
            $dataItems = $serializer->normalize($items, 'json', ['groups' => ['carts', 'cart-items'],
                'circular_reference_handler' => function ($object) {
                    return $object->getId();
                }
            ]);
            return new JsonResponse($dataItems, 200);
        } catch(Throwable $e) {
            $this->logger->error('Erreur de la récupération des produit du panier', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Erreur interne du serveur'], 500);
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
                $cart->setUser($user);
                $this->entityManager->persist($cart);
            }

            foreach ($data as $item) {
                $product = $this->entityManager->getRepository(Product::class)->findOneBy(['id' => $item['id']]);
                if (!$product) {
                    return new JsonResponse(['error' => 'Produit non trouvé'], 404);
                }

                if ($item['quantity'] <= 0) {
                    return new JsonResponse(['error' => 'Quantité invalide'], 400);
                }

                $itemExisting = $this->entityManager->getRepository(CartItem::class)->findOneBy(['cart' => $cart, 'product' => $product]);
                if ($itemExisting) {
                    $itemExisting->setQuantity($itemExisting->getQuantity() + $item['quantity']);
                    $this->entityManager->persist($itemExisting);
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
            } catch(Exception $e) {
                $this->logger->error('Erreur lors de l\'ajout d\'un produit au panier', ['error' => $e->getMessage()]);
                return new JsonResponse(['error' => 'Erreur interne du serveur'], 500);
            }

            return new JsonResponse(['success' => true, 'message' => 'Item added to cart'], 201);
        } catch (Throwable $e) {
            $this->logger->error('Erreur lors de l\'ajout d\'un produit au panier', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Erreur interne du serveur'], 500);
        }
    }

    #[Route('/delete/{id}', methods: ['DELETE'])]
    public function deleteItemExisting(int $id): JsonResponse
    {
        try {
            $user = $this->getUser();

            $cart = $this->entityManager->getRepository(Cart::class)->findOneBy(['user' => $user]);
            if (!$cart) {
                return new JsonResponse(['error' => 'Panier non trouvé'], 404);
            }

            $productExisting = $this->entityManager->getRepository(CartItem::class)->findOneBy(['cart' => $cart, 'id' => $id]);
            if (!$productExisting) {
                return new JsonResponse(['error' => 'Produit non existant'], 404);
            }

            if ($productExisting->getQuantity() > 1) {
                $productExisting->setQuantity($productExisting->getQuantity() - 1);
                $this->entityManager->persist($productExisting);
            } else {
                $this->entityManager->remove($productExisting);
            }

            try {
                $this->entityManager->flush();
            } catch(Exception $e) {
                $this->logger->error('Erreur de la suppresion d\'un produit', ['error' => $e->getMessage()]);
                return new JsonResponse(['error' => 'Erreur interne du serveur'], 500);
            }

            return new JsonResponse(['success' => true, 'message' => 'Item deleted successfully'], 200);
        } catch(Throwable $e) {
            $this->logger->error('Erreur de la suppression d\'un produit du panier', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Erreur interne du serveur'], 500);
        }
    }

    #[Route('/add/{id}', methods: ['POST'])]
    public function addItemExisting(int $id): JsonResponse
    {
        try {
            $user = $this->getUser();

            $cart = $this->entityManager->getRepository(Cart::class)->findOneBy(['user' => $user]);
            if (!$cart) {
                return new JsonResponse(['error' => 'Panier inexistant'], 404);
            }

            $cartItemExisting = $this->entityManager->getRepository(CartItem::class)->findOneBy(['cart' => $cart, 'id' => $id]);
            if (!$cartItemExisting) {
                return new JsonResponse(['error' => 'produit inexistant'], 404);
            }

            $cartItemExisting->setQuantity($cartItemExisting->getQuantity() + 1);
            $this->entityManager->persist($cartItemExisting);

            try {
                $this->entityManager->flush();
            } catch(Exception $e) {
                $this->logger->error('Erreur : ajout d\'un produit du panier', ['error' => $e->getMessage()]);
                return new JsonResponse(['error' => 'Erreur interne du serveur'], 500);
            }

            return new JsonResponse(['success' => true, 'message' => 'Item added to cart'], 201);
        } catch(Throwable $e) {
            $this->logger->error('Erreur : ajout d\'un produit du panier', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Erreur interne du serveur'], 500);
        }
    }


}

