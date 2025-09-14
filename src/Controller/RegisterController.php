<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Test\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegisterController extends AbstractController
{
    #[Route('/api/register', methods: ['POST'])]
    public function register(Request $request, UserPasswordHasherInterface $userPassword, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $user = new User();
            $form = $this->createForm(UserType::class, $user);
            $data = json_decode($request->getContent(), true);
            $form->submit($data);
            if ($form->isValid() && $form->isSubmitted()) {
                $user->setRoles(['ROLE_ADMIN']);
                $user->setPassword($userPassword->hashPassword(
                    $user,
                    $form->get('password')->getData()
                ));
                $entityManager->persist($user);
                $entityManager->flush();
                return new JsonResponse(['message' => 'Inscription réussie'], 201);
            } else {
                return new JsonResponse($this->getErrorMessages($form), 400);
            }
        } catch(\Exception $e) {
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
