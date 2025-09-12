<?php

namespace App\Controller;

use App\Contract\UserServiceInterface;
use App\Entity\User;
use App\Exception\EmailAlreadyExistsException;
use App\Exception\InvalidInputException;
use App\Exception\ValidationException;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Authentication Controller
 */
#[Route('/api')]
#[OA\Tag(name: 'Authentication')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserServiceInterface $userService,
    )
    {
    }

    #[Route('/registration', name: 'api_registration', methods: ['POST'])]
    #[OA\Post(
        path: '/api/registration',
        description: 'Create a new User and return JWT Token',
        requestBody: new OA\RequestBody(ref: '#/components/requestBodies/RegistrationRequest'),
        responses: [
            new OA\Response(ref: '#/components/responses/RegistrationSuccess', response: 201),
            new OA\Response(ref: '#/components/responses/ValidationError', response: 400),
            new OA\Response(ref: '#/components/responses/EmailExists', response: 409),
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
        requestBody: new OA\RequestBody(ref: '#/components/requestBodies/LoginRequest'),
        responses: [
            new OA\Response(ref: '#/components/responses/LoginSuccess', response: 200),
            new OA\Response(ref: '#/components/responses/InvalidCredentials', response: 401)
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
            new OA\Response(ref: '#/components/responses/ProfileSuccess', response: 200),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
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
