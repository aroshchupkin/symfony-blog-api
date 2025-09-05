<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserRepository              $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface          $validator,
        private readonly SerializerInterface         $serializer,
        private readonly JWTTokenManagerInterface    $JWTTokenManager
    )
    {
    }

    #[Route('/registration', name: 'api_registration', methods: ['POST'])]
    public function registration(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse([
                'error' => 'Invalid JSON body',
                'code' => 'INVALID_JSON'
            ], Response::HTTP_BAD_REQUEST);
        }

        $requiredFields = ['username', 'email', 'password'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                return new JsonResponse([
                    'error' => "Field '{$field}' is required",
                    'code' => 'MISSING_FIELD'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($this->userRepository->emailExists($data['email'])) {
            return new JsonResponse([
                'error' => 'Email already exists.',
                'code' => 'EMAIL_EXISTS'
            ], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setUsername($data['username']);
        $user->setEmail($data['email']);
        $user->setPlainPassword($data['password']);

        $errors = $this->validator->validate($user, null, ['registration']);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return new JsonResponse([
                'error' => 'Validation failed',
                'code' => 'VALIDATION_ERROR',
                'details' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $user->getPlainPassword());
        $user->setPassword($hashedPassword);
        $user->eraseCredentials();

        $this->userRepository->save($user, true);

        $token = $this->JWTTokenManager->create($user);

        $userData = $this->serializer->serialize($user, 'json', ['groups' => ['user:read']]);

        return new JsonResponse([
            'message' => 'User registered successfully',
            'code' => 'REGISTRATION_SUCCESS',
            'user' => json_decode($userData, true),
            'token' => $token
        ], Response::HTTP_CREATED);
    }

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Login endpoint. Handled by JWT Security'
        ]);
    }

    #[Route('/profile', name: 'api_profile', methods: ['GET'])]
    public function profile(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'User not authenticated',
                'code' => 'NOT_AUTHENTICATED'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $userData = $this->serializer->serialize($user, 'json', ['groups' => ['user:read']]);

        return new JsonResponse([
            'user' => json_decode($userData, true),
        ], Response::HTTP_OK);
    }
}
