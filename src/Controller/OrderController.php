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
    public function index(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

            $user = $this->getUser();

            $order = new Order();
            $order->setUser($user);

            $form = $this->createForm(OrderType::class, $order);
            $form->submit($data);

            if ($form->isSubmitted() && $form->isValid()) {
                $cart = $this->entityManager->getRepository(Cart::class)->findOneBy(['user' => $user]);
                $items = $cart->getItems();
                foreach ($items as $item) {
                    $orderItems = new OrderItems();
                    $orderItems->setProduct($item->getProduct());
                    $orderItems->setPrice($item->getPrice());
                    $orderItems->setQuantity($item->getQuantity());
                    $orderItems->setOrder($order);
                    $this->entityManager->persist($orderItems);
                    $this->entityManager->remove($item);
                }

                $this->entityManager->persist($order);

                try {
                    $this->entityManager->flush();
                } catch(Exception $e) {
                    $this->logger->error('Error add order', ['error' => $e->getMessage()]);
                    return new JsonResponse(['error' => $e->getMessage()], 500);
                }
            } else {
                $errors = $this->getErrorMessages($form);
                return new JsonResponse(['error' => $errors], 500);
            }

            return new JsonResponse(['success' => true, 'message' => 'Add order'], 201);
        } catch (Throwable $e) {
            $this->logger->error('Error add order', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], 500);
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
