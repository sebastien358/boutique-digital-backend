<?php

namespace App\Controller;

use Throwable;
use Exception;
use App\Entity\Cart;
use App\Entity\User;
use App\Form\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Test\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class RegisterController extends AbstractController
{
    private $passwordHasher;
    private $entityManager;

    private $logger;

    public function __construct(
        UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->passwordHasher = $passwordHasher;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    #[Route('/register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $user = new User();
            $form = $this->createForm(UserType::class, $user);
            $form->submit($data);

            if (!$form->isValid()) {
                $errors = $this->getErrorMessages($form);
                return new JsonResponse(['error' => $errors], 400);
            }

            $user->setRoles(['ROLE_USER']);
            $user->setPassword($this->passwordHasher->hashPassword($user,
                $form->get('password')->getData())
            );

            $cart = new Cart();
            $user->setCart($cart);
            $cart->setUser($user);

            $this->entityManager->persist($user);
            $this->entityManager->persist($cart);

            try {
                $this->entityManager->flush();
            } catch (Exception $e) {
                $this->logger->error('Erreur lors de l\'enregistrement de l\'utilisateur : ', ['error' => $e->getMessage()]);
                return new JsonResponse(['error' => $e->getMessage()], 500);
            }

            return new JsonResponse(['success' => true, 'message' => 'Inscription réussie'], 201);
        } catch (Throwable $e) {
            $this->logger->error('Erreur lors de l\'enregistrement de l\'utilisateur : ', ['error' => $e->getMessage()]);
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
