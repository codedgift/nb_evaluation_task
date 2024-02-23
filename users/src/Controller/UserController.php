<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Service\RabbitMQService;
use App\Repository\UsersRepository;
use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserController extends AbstractController
{
    private RabbitMQService $rabbitMQService;
    private EntityManagerInterface $entityManager;
    private UsersRepository $usersRepository;
    private ValidatorInterface $validator;

    public function __construct(
        RabbitMQService $rabbitMQService,
        EntityManagerInterface $entityManager,
        UsersRepository $usersRepository,
        ValidatorInterface $validator
    ) {
        $this->rabbitMQService = $rabbitMQService;
        $this->entityManager = $entityManager;
        $this->usersRepository = $usersRepository;
        $this->validator = $validator;
    }

    #[Route('/users', name: 'send_message', methods: ['POST'])]
    public function index(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (null === $data) {
            return new JsonResponse(['error' => 'Invalid JSON data'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user = new Users();
        $user->setEmail($data['email'] ?? '');
        $user->setFirstName($data['firstName'] ?? '');
        $user->setLastName($data['lastName'] ?? '');

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            return new JsonResponse(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        try {
            $this->rabbitMQService->sendMessage('notification', json_encode($data));
            return new JsonResponse(['message' => 'User created and message sent!'], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            // Consider logging the error here
            return new JsonResponse(['error' => 'Can\'t connect to message broker!'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
