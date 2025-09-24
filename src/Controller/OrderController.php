<?php

namespace App\Controller;

use Throwable;
use Exception;
use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\OrderItems;
use App\Form\OrderType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Test\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route(['/api/order'])]
#[IsGranted('ROLE_USER')]
final class OrderController extends AbstractController
{
    private $entityManager;
    private $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    #[Route('/list', methods: ['GET'])]
    public function list(Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $user = $this->getUser();
            $page = $request->query->get('page', 1);
            $limit = $request->query->get('limit', 3);

            $orders = $this->entityManager->getRepository(Order::class)->findOrdersByUserPaginated($user, $page, $limit);
            if (!$orders) {
                return new JsonResponse(['message' => 'Le panier est vide'], 404);
            }

            $dataOrders = $serializer->normalize($orders, 'json', ['groups' => [
                'orders', 'order_items', 'products'],
                'circular_reference_handler' => function ($object) {
                    return $object->getId();
                }
            ]);

            $totalOrders = $this->entityManager->getRepository(Order::class)->countOrdersByUserPaginated($user);

            return new JsonResponse([
                'orders' => $dataOrders,
                'total' => $totalOrders,
            ], 200);
        } catch (Throwable $e) {
            $this->logger->error('error de la récupération des commandes', ['message' => $e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/new', methods: ['POST'])]
    public function index(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

            $user = $this->getUser();

            $order = new Order();
            $order->setUser($user);

            $form = $this->createForm(OrderType::class, $order);
            $form->submit($data);

            if ($form->isSubmitted() && $form->isValid()) {
                $cart = $entityManager->getRepository(Cart::class)->findOneBy(['user' => $user]);
                $cartItems = $cart->getItems();
                foreach ($cartItems as $item) {
                    $orderItem = new OrderItems();
                    $orderItem->setProduct($item->getProduct());
                    $orderItem->setQuantity($item->getQuantity());
                    $orderItem->setPrice($item->getPrice());
                    $orderItem->setOrder($order);
                    $order->addOrderItem($orderItem);
                    $entityManager->persist($orderItem);
                }

                $entityManager->persist($order);

                try {
                    $entityManager->flush();
                } catch (Exception $e) {
                    $this->logger->error('Erreur de la commande : ', ['error' => $e->getMessage()]);
                    return new JsonResponse(['error' => 'Erreur interne du serveur'], 500);
                }

                return new JsonResponse(['success' => true, 'message' => 'Commande créée avec succès'], 201);
            } else {
                $errors = $this->getErrorMessages($form);
                return new JsonResponse(['errors' => $errors], 400);
            }
        } catch (Throwable $e) {
            $this->logger->error('Erreur de la commande : ', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Erreur interne du serveur'], 500);
        }
    }

    private function getErrorMessages(FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors() as $key => $error) {
            $errors[] = $error->getMessage();
        }
        foreach ($form->all() as $child) {
            if ($child->isSubmitted() && !$child->isValid()) {
                $errors[$child->getName()] = $this->getErrorMessages($child);
            }
        }
        return $errors;
    }
}
