<?php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\User;
use App\Repository\PostRepository;
use App\Service\CacheService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/api/posts')]
#[OA\Tag(name: 'Posts')]
final class PostController extends AbstractController
{
    public function __construct(
        private readonly PostRepository      $postRepository,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface  $validator,
        private readonly CacheService        $cacheService
    )
    {
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('', name: 'posts_index', methods: ['GET'])]
    #[OA\Get(
        path: '/api/posts',
        description: 'Return paginated list of blog posts',
        parameters: [
            new OA\Parameter(
                name: 'page',
                description: 'Page number',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
            ),
            new OA\Parameter(
                name: 'limit',
                description: 'Items per page',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 10, maximum: 100, minimum: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Posts list with pagination',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'posts',
                            type: 'array',
                            items: new OA\Items(ref: new Model(type: Post::class, groups: ['post:list']))
                        ),
                        new OA\Property(
                            property: 'pagination',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer'),
                                new OA\Property(property: 'total_pages', type: 'integer'),
                                new OA\Property(property: 'total_items', type: 'integer'),
                                new OA\Property(property: 'items_per_page', type: 'integer'),
                                new OA\Property(property: 'has_next_page', type: 'boolean'),
                                new OA\Property(property: 'has_previous_page', type: 'boolean')
                            ],
                            type: 'object'
                        )
                    ]
                )
            )
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = min(100, max(1, (int)$request->query->get('limit', 10)));

        $cacheKey = $this->cacheService->getPostsListCacheKey($page, $limit);

        $result = $this->cacheService->getCache()->get($cacheKey, function (ItemInterface $item) use ($page, $limit) {
            $item->expiresAfter($this->cacheService->getListCacheTime());

            $posts = $this->postRepository->findAllWithPagination($page, $limit);
            $total = $this->postRepository->countAll();
            $totalPages = (int)ceil($total / $limit);

            return [
                'posts' => $posts,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $total,
                    'items_per_page' => $limit,
                    'has_next_page' => $page < $totalPages,
                    'has_previous_page' => $page > 1,
                ]
            ];
        });

        $data = $this->serializer->serialize($result, 'json', ['groups' => ['post:list']]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/{id}', name: 'posts_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/posts/{id}',
        description: 'Return detailed information about a specific post with comments',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Post ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Post details',
                content: new OA\JsonContent(ref: new Model(type: Post::class, groups: ['post:read']))
            ),
            new OA\Response(
                response: 404,
                description: 'Post not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Post not found'),
                        new OA\Property(property: 'code', type: 'string', example: 'POST_NOT_FOUND')
                    ]
                )
            )
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $cacheKey = $this->cacheService->getPostDetailCacheKey($id);

        $post = $this->cacheService->getCache()->get($cacheKey, function (ItemInterface $item) use ($id) {
            $item->expiresAfter($this->cacheService->getListCacheTime());

            return $this->postRepository->find($id);
        });

        if (!$post) {
            return new JsonResponse([
                'error' => 'Post not found',
                'code' => 'POST_NOT_FOUND'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $this->serializer->serialize($post, 'json', ['groups' => ['post:read']]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('', name: 'posts_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/posts',
        description: 'Creates a new blog post (authentication required)',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            description: 'Post data',
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'content'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'My Blog Post'),
                    new OA\Property(property: 'content', type: 'string', example: 'This is the content of my blog post...')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Post created successfully',
                content: new OA\JsonContent(ref: new Model(type: Post::class, groups: ['post:read']))
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
                response: 401,
                description: 'Authentication required',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'User not authenticated'),
                        new OA\Property(property: 'code', type: 'string', example: 'USER_NOT_AUTHENTICATED')
                    ]
                )
            )
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse([
                'error' => 'Invalid JSON body',
                'code' => 'INVALID_JSON'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'User not authenticated',
                'code' => 'USER_NOT_AUTHENTICATED'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $post = new Post();
        $post->setAuthor($user);

        if (isset($data['title'])) {
            $post->setTitle($data['title']);
        }

        if (isset($data['content'])) {
            $post->setContent($data['content']);
        }

        $errors = $this->validator->validate($post);

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

        $this->postRepository->save($post, true);

        $this->cacheService->clearPostsListCache();

        $data = $this->serializer->serialize($post, 'json', ['groups' => ['post:read']]);

        return new JsonResponse($data, Response::HTTP_CREATED, [], true);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/{id}', name: 'posts_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[OA\Patch(
        path: '/api/posts/{id}',
        description: 'Updates an existing post (only author can update)',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            description: 'Updated post data',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Updated Blog Post Title'),
                    new OA\Property(property: 'content', type: 'string', example: 'Updated content...')
                ]
            )
        ),
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Post ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Post updated successfully',
                content: new OA\JsonContent(ref: new Model(type: Post::class, groups: ['post:read']))
            ),
            new OA\Response(
                response: 400,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Validation failed'),
                        new OA\Property(property: 'code', type: 'string', example: 'VALIDATION_ERROR')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Authentication required',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'User not authenticated'),
                        new OA\Property(property: 'code', type: 'string', example: 'USER_NOT_AUTHENTICATED')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Access denied',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Access denied. You can only update your own posts'),
                        new OA\Property(property: 'code', type: 'string', example: 'ACCESS_DENIED')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Post not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Post not found'),
                        new OA\Property(property: 'code', type: 'string', example: 'POST_NOT_FOUND')
                    ]
                )
            )
        ]
    )]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'User not authenticated',
                'code' => 'USER_NOT_AUTHENTICATED'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $post = $this->postRepository->find($id);

        if (!$post) {
            return new JsonResponse([
                'error' => 'Post not found',
                'code' => 'POST_NOT_FOUND'
            ], Response::HTTP_NOT_FOUND);
        }

        if ($post->getAuthor() !== $user) {
            return new JsonResponse([
                'error' => 'Access denied. You can only update your own posts',
                'code' => 'ACCESS_DENIED'
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse([
                'error' => 'Invalid JSON body',
                'code' => 'INVALID_JSON'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['title'])) {
            $post->setTitle($data['title']);
        }

        if (isset($data['content'])) {
            $post->setContent($data['content']);
        }

        $errors = $this->validator->validate($post);

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

        $this->postRepository->save($post, true);

        $this->cacheService->clearPostDetailCache($id);
        $this->cacheService->clearPostsListCache();

        $data = $this->serializer->serialize($post, 'json', ['groups' => ['post:read']]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/{id}', name: 'posts_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/posts/{id}',
        description: 'Deletes a post (only author can delete)',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Post ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Post deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Post deleted successfully'),
                        new OA\Property(property: 'code', type: 'string', example: 'POST_DELETED')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Authentication required',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'User not authenticated'),
                        new OA\Property(property: 'code', type: 'string', example: 'USER_NOT_AUTHENTICATED')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Access denied',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Access denied. You can only delete your own posts'),
                        new OA\Property(property: 'code', type: 'string', example: 'ACCESS_DENIED')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Post not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Post not found'),
                        new OA\Property(property: 'code', type: 'string', example: 'POST_NOT_FOUND')
                    ]
                )
            )
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

        $post = $this->postRepository->find($id);

        if (!$post) {
            return new JsonResponse([
                'error' => 'Post not found',
                'code' => 'POST_NOT_FOUND'
            ], Response::HTTP_NOT_FOUND);
        }

        if ($post->getAuthor() !== $user) {
            return new JsonResponse([
                'error' => 'Access denied. You can only delete your own posts',
                'code' => 'ACCESS_DENIED'
            ], Response::HTTP_FORBIDDEN);
        }

        $this->postRepository->remove($post, true);

        $this->cacheService->clearPostRelatedCache($id);

        return new JsonResponse([
            'message' => 'Post deleted successfully',
            'code' => 'POST_DELETED'
        ], Response::HTTP_OK);
    }
}
