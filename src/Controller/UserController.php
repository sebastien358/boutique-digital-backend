<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    #[Route('/api/email-exists', methods: ['POST'])]
    public function emailExists(Request $request, UserRepository $userRepository): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (isset($data['email'])) {
                $userEmail = $data['email'];
                $emailExists = $userRepository->findOneBy(['email' => $userEmail]);
                if ($emailExists) {
                    return new JsonResponse(['exists' => true]);
                } else {
                    return new JsonResponse(['exists' => false]);
                }
            }
        } catch(\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
