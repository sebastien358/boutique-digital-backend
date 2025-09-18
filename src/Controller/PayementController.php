<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Stripe\Stripe;

#[Route('/api')]
#[IsGranted("ROLE_USER")]
final class PayementController extends AbstractController
{
    #[Route('/payement', methods: ['POST'])]
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['error' => 'Vous devez être connecté pour effectuer un paiement'], 401);
            }

            $token = $request->request->get('token');
            if (!$token) {
                return new JsonResponse(['error' => 'Token de paiement manquant'], 400);
            }

            Stripe::setApiKey('');

            $charge = \Stripe\Charge::create([
                'amount' => 1000, // Montant à débiter (en centimes)
                'currency' => 'eur', // Devise
                'source' => $token,
                'description' => 'Paiement pour ' . $user->getFirstname() . ' ' . $user->getLastname()
            ]);

            return new JsonResponse(['success' => true, 'message' => 'Paiement réussi !']);
        } catch (\Stripe\Exception\CardException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
