<?php

namespace App\Controller\Admin;

use Throwable;
use Exception;
use App\Entity\Order;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/admin/order')]
#[IsGranted('ROLE_ADMIN')]
final class OrderAdminController extends AbstractController
{
    private $entityManager;
    private $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    #[Route('/list', methods: ['GET'])]
    public function commands(Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $page = $request->query->getInt('page', 1);
            $limit = $request->query->getInt('limit', 3);

            $orders = $this->entityManager->getRepository(Order::class)->findAllOrder($page, $limit);
            $dataOrders = $serializer->normalize($orders, 'json', ['groups' => ['orders', 'order_items', 'products'],
                'circular_reference_handler' => function ($object) {
                    return $object->getId();
                }
            ]);
            $total = $this->entityManager->getRepository(Order::class)->countAllOrder();
            return new JsonResponse([
                'orders' => $dataOrders,
                'total' =>$total
            ], 200);
        } catch (Throwable $e) {
            $this->logger->error('Erreur récupération des commandes', ['message' => $e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/delete/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $order = $this->entityManager->getRepository(Order::class)->findOneBy(['id' => $id]);
            if (!$order) {
                return new JsonResponse(['message' => 'Order not found'], 404);
            }
            $this->entityManager->remove($order);

            try {
                $this->entityManager->flush();
            } catch (Exception $e) {
                $this->logger->error('Erreur suppression des commandes', ['message' => $e->getMessage()]);
                return new JsonResponse(['error' => $e->getMessage()], 500);
            }

            return new JsonResponse(['success' => true, 'message' => 'La commande a bien été supprimée'], 200);
        } catch(Throwable $e) {
            $this->logger->error('Erreur de la suppressin d\'une commande', ['message' => $e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
