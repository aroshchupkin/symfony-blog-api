<?php

namespace App\Controller;

use App\Entity\User;
use App\Exception\EmailAlreadyExistsException;
use App\Exception\InvalidInputException;
use App\Exception\ValidationException;
use App\Service\UserService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
#[OA\Tag(name: 'Authentication')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
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

        try {
            $user = $this->userService->registerUser($data);
            $token = $this->userService->generateToken($user);
            $userData = $this->userService->serializeUser($user);

            return new JsonResponse([
                'message' => 'User successfully registered',
                'code' => 'REGISTRATION_SUCCESS',
                'user' => $userData,
                'token' => $token,
            ], Response::HTTP_CREATED);
        } catch (InvalidInputException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'INVALID_INPUT'
            ], Response::HTTP_BAD_REQUEST);
        } catch (EmailAlreadyExistsException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'EMAIL_EXISTS'
            ], Response::HTTP_CONFLICT);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'VALIDATION_ERROR',
                'details' => $e->getValidationErrors()
            ], Response::HTTP_BAD_REQUEST);
        }
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

        $userData = $this->userService->serializeUser($user);

        return new JsonResponse([
            'user' => $userData,
        ], Response::HTTP_OK);
    }
}
