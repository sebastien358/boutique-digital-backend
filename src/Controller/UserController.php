<?php

namespace App\Controller;

use Throwable;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/api/user')]
final class UserController extends AbstractController
{
    private $userRepository;
    private $logger;

    public function __construct(UserRepository $userRepository, LoggerInterface $logger)
    {
        $this->userRepository = $userRepository;
        $this->logger = $logger;
    }
    #[Route('/me')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function me(NormalizerInterface $normalizer): JsonResponse
    {
        try {
            $user = $this->getUser();

            $dataUser = $normalizer->normalize($user, 'json', ['groups' => 'user']);
            return new JsonResponse($dataUser, 200);
        } catch(Throwable $e) {
            $this->logger->error('Erreur de la récupérarion des infos d\'un utilisateur : ', ['message' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Erreur interne du serveur'], 500);
        }
    }

    #[Route('/email-exists', methods: ['POST'])]
    public function emailExists(Request $request, UserRepository $userRepository): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $emailExisting = $userRepository->findOneBy(['email' => $data['email']]);
            if ($emailExisting) {
                return new JsonResponse(['exists' => true, 'message' => 'Email existant']);
            } else {
                return new JsonResponse(['exists' => false, 'message' => 'Email non valide']);
            }
        } catch(Throwable $e) {
            $this->logger->error('Erreur de la récupérarion des infos d\'un utilisateur : ', ['message' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Erreur interne du serveur'], 500);
        }
    }
}
