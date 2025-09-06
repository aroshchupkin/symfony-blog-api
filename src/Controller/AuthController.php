<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
#[OA\Tag(name: 'Authentication')]
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
    #[OA\Post(
        path: '/api/registration',
        description: 'Create a new User and return JWT Token',
        requestBody: new OA\RequestBody(
            description: 'Register a new user',
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'Joey'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'joey@gmail.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'joey@gmail.com'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User registered successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'User registered successfully'),
                        new OA\Property(property: 'code', type: 'string', example: 'REGISTRATION_SUCCESS'),
                        new OA\Property(property: 'user', ref: new Model(type: User::class, groups: ['user:read'])),
                        new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE3NTcxNTQ3NjIsImV4cCI6MTc1NzE1ODM2Miwicm9sZXMiOlsiUk9MRV9VU0VSIl0sImVtYWlsIjoiam9leUBnbWFpbC5jb20ifQ.bhx4mNcYrJsYxcPOROWrGql-gX8qd8Iqx3jOzELUtyM5iGitWFkqoIQqjhGjO2j8jbHPUY5vect4Ap8tKSo0quBRubWZf_p83qPM4Y9G6eJpbyhOe90PpvTJkCW6PGDOh67o_8YNBBW_JTxd8z-HDWp2_dOQuKVPpxd8yckaIh6FxbVR8IjSx118jbkkjHE5abyHuGHHg52TzNFaX48PerD9SCdiwlGwriCziBqEJ0egnwEoPoqFwLq9aUOKHFSuKZ8uUC-GKIO2Dj6GN7go87o51pXfB6enw7dsHkCAEWE-DiVd_4IcYYErzEr8sdWYjNIcQ31XGq8ULHvrl1mGzw'),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Validation failed'),
                        new OA\Property(property: 'code', type: 'string', example: 'VALIDATION_ERROR'),
                        new OA\Property(property: 'details', type: 'object')
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'Email already exists',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Email already exists.'),
                        new OA\Property(property: 'code', type: 'string', example: 'EMAIL_EXISTS'),
                    ]
                )
            )
        ]
    )]
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
    #[OA\Post(
        path: '/api/login',
        description: 'Authenticate User and return JWT token',
        requestBody: new OA\RequestBody(
            description: 'User credentials',
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'joey@gmail.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'joey@gmail.com')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE3NTcxNTQ3NjIsImV4cCI6MTc1NzE1ODM2Miwicm9sZXMiOlsiUk9MRV9VU0VSIl0sImVtYWlsIjoiam9leUBnbWFpbC5jb20ifQ.bhx4mNcYrJsYxcPOROWrGql-gX8qd8Iqx3jOzELUtyM5iGitWFkqoIQqjhGjO2j8jbHPUY5vect4Ap8tKSo0quBRubWZf_p83qPM4Y9G6eJpbyhOe90PpvTJkCW6PGDOh67o_8YNBBW_JTxd8z-HDWp2_dOQuKVPpxd8yckaIh6FxbVR8IjSx118jbkkjHE5abyHuGHHg52TzNFaX48PerD9SCdiwlGwriCziBqEJ0egnwEoPoqFwLq9aUOKHFSuKZ8uUC-GKIO2Dj6GN7go87o51pXfB6enw7dsHkCAEWE-DiVd_4IcYYErzEr8sdWYjNIcQ31XGq8ULHvrl1mGzw'),
                        new OA\Property(property: 'user', ref: new Model(type: User::class, groups: ['user:read']))
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Invalid credentials',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid credentials.')
                    ]
                )
            )
        ]
    )]
    public function login(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Handled by Security'
        ]);
    }

    #[Route('/profile', name: 'api_profile', methods: ['GET'])]
    #[OA\Get(
        path: '/api/profile',
        description: 'Return Authenticated User',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User profile data',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'user', ref: new Model(type: User::class, groups: ['user:read']))
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'User not authenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'User not authenticated'),
                        new OA\Property(property: 'code', type: 'string', example: 'NOT_AUTHENTICATED')
                    ]
                )
            )
        ]
    )]
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
