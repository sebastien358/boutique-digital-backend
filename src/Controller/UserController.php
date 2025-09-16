<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/api/user')]
final class UserController extends AbstractController
{
    #[Route('/me')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function me(NormalizerInterface  $normalizer): JsonResponse
    {
        try {
            $user = $this->getUser();
            $dataUser = $normalizer->normalize($user, 'json', ['groups' => 'user']);

            return new JsonResponse($dataUser);
        } catch(\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/email-exists', methods: ['POST'])]
    public function emailExists(Request $request, UserRepository $userRepository): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (isset($data['email'])) {
                $emailExists = $userRepository->findOneBy(['email' => $data['email']]);
                if ($emailExists) {
                    return new JsonResponse([
                        'exists' => true
                    ]);
                } else {
                    return new JsonResponse([
                        'exists' => false
                    ]);
                }
            }
        } catch(\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
