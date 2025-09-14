<?php

namespace App\Controller;

use App\Contract\PaginationServiceInterface;
use App\Contract\PostServiceInterface;
use App\Entity\User;
use App\Exception\AccessDeniedException;
use App\Exception\InvalidInputException;
use App\Exception\PostNotFoundException;
use App\Exception\ValidationException;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Post Controller
 */
#[Route('/api/posts')]
#[OA\Tag(name: 'Posts')]
final class PostController extends AbstractController
{
    public function __construct(
        private readonly PostServiceInterface $postService,
        private readonly SerializerInterface  $serializer,
        private readonly PaginationServiceInterface $paginationService
    )
    {
    }

    #[Route('', name: 'posts_index', methods: ['GET'])]
    #[OA\Get(
        path: '/api/posts',
        description: 'Return paginated list of blog posts',
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/PageParameter'),
            new OA\Parameter(ref: '#/components/parameters/LimitParameter')
        ],
        responses: [
            new OA\Response(ref: '#/components/responses/PostListSuccess', response: 200)
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        ['page' => $page, 'limit' => $limit] = $this->paginationService->validatePagination($request);

        try {
            $result = $this->postService->getPostsWithPagination($page, $limit);
            $data = $this->serializer->serialize($result, 'json', ['groups' => ['post:list']]);

            return new JsonResponse($data, Response::HTTP_OK, [], true);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'INVALID_ARGUMENT'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'posts_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/posts/{id}',
        description: 'Return detailed information about a specific post with comments',
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/PostIdParameter')
        ],
        responses: [
            new OA\Response(ref: '#/components/responses/PostSuccess', response: 200),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $post = $this->postService->getPostById($id);
            $data = $this->serializer->serialize($post, 'json', ['groups' => ['post:read']]);

            return new JsonResponse($data, Response::HTTP_OK, [], true);
        } catch (PostNotFoundException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'POST_NOT_FOUND'
            ], Response::HTTP_NOT_FOUND);

        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'INVALID_ARGUMENT'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('', name: 'posts_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/posts',
        description: 'Creates a new blog post (authentication required)',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(ref: '#/components/requestBodies/PostRequest'),
        responses: [
            new OA\Response(ref: '#/components/responses/PostCreated', response: 201),
            new OA\Response(ref: '#/components/responses/ValidationError', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'User not authenticated',
                'code' => 'USER_NOT_AUTHENTICATED'
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $post = $this->postService->createPost($data, $user);
            $postData = $this->postService->serializePost($post);

            return new JsonResponse($postData, Response::HTTP_CREATED);

        } catch (InvalidInputException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'INVALID_INPUT'
            ], Response::HTTP_BAD_REQUEST);

        } catch (ValidationException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'VALIDATION_ERROR',
                'details' => $e->getValidationErrors()
            ], Response::HTTP_BAD_REQUEST);

        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'INVALID_ARGUMENT'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'posts_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[OA\Patch(
        path: '/api/posts/{id}',
        description: 'Updates an existing post (only author can update)',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(ref: '#/components/requestBodies/PostRequest'),
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/PostIdParameter')
        ],
        responses: [
            new OA\Response(ref: '#/components/responses/PostUpdated', response: 200),
            new OA\Response(ref: '#/components/responses/ValidationError', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        ]
    )]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'User not authenticated',
                'code' => 'USER_NOT_AUTHENTICATED'
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $post = $this->postService->updatePost($id, $data, $user);
            $postData = $this->postService->serializePost($post);

            return new JsonResponse($postData, Response::HTTP_OK);

        } catch (PostNotFoundException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'POST_NOT_FOUND'
            ], Response::HTTP_NOT_FOUND);

        } catch (AccessDeniedException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'ACCESS_DENIED'
            ], Response::HTTP_FORBIDDEN);

        } catch (InvalidInputException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'INVALID_INPUT'
            ], Response::HTTP_BAD_REQUEST);

        } catch (ValidationException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'VALIDATION_ERROR',
                'details' => $e->getValidationErrors()
            ], Response::HTTP_BAD_REQUEST);

        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'INVALID_ARGUMENT'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'posts_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/posts/{id}',
        description: 'Deletes a post (only author can delete)',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/PostIdParameter')
        ],
        responses: [
            new OA\Response(ref: '#/components/responses/PostDeleted', response: 200),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'User not authenticated',
                'code' => 'USER_NOT_AUTHENTICATED'
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->postService->deletePost($id, $user);

            return new JsonResponse([
                'message' => 'Post deleted successfully',
                'code' => 'POST_DELETED'
            ], Response::HTTP_OK);

        } catch (PostNotFoundException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'POST_NOT_FOUND'
            ], Response::HTTP_NOT_FOUND);

        } catch (AccessDeniedException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'ACCESS_DENIED'
            ], Response::HTTP_FORBIDDEN);

        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'INVALID_ARGUMENT'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
