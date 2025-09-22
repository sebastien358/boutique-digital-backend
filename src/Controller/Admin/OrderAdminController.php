<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Repository\CartRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/admin/command')]
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
    public function commands(SerializerInterface $serializer): JsonResponse
    {
        try {
            $orders = $this->entityManager->getRepository(Order::class)->findAll();

            $dataOrders = $serializer->normalize($orders, 'json', [
                'groups' => ['orders', 'order_items', 'products'],
                'circular_reference_handler' => function ($object) {
                    return $object->getId();
                }
            ]);
            return new JsonResponse($dataOrders, 200);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur récupération des commandes', ['message' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Erreur interne serveur'], 500);
        }
    }

}
