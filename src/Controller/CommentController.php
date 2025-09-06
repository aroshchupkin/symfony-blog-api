<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\User;
use App\Repository\CommentRepository;
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

#[Route('/api/posts/{postId}/comments')]
#[OA\Tag(name: 'Comments')]
final class CommentController extends AbstractController
{
    public function __construct(
        private readonly CommentRepository   $commentRepository,
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
    #[Route('', name: 'comments_index', requirements: ['postId' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/posts/{postId}/comments',
        description: 'Return paginated list of comments for a specific post',
        parameters: [
            new OA\Parameter(
                name: 'postId',
                description: 'Post ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
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
                description: 'Comments list with pagination',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'comments',
                            type: 'array',
                            items: new OA\Items(ref: new Model(type: Comment::class, groups: ['comment:list']))
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
    public function index(int $postId, Request $request): JsonResponse
    {
        $post = $this->postRepository->find($postId);

        if (!$post) {
            return new JsonResponse([
                'error' => 'Post not found',
                'code' => 'POST_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        $page = max(1, (int)$request->query->get('page', 1));
        $limit = min(100, max(1, (int)$request->query->get('limit', 10)));

        $cacheKey = $this->cacheService->getCommentsListCacheKey($postId, $page, $limit);

        $result = $this->cacheService->getCache()->get($cacheKey, function (ItemInterface $item) use ($post, $page, $limit) {
            $item->expiresAfter($this->cacheService->getListCacheTime());

            $comments = $this->commentRepository->findByPostWithPagination($post, $page, $limit);
            $total = $this->commentRepository->countByPost($post);
            $totalPages = (int)ceil($total / $limit);

            return [
                'comments' => $comments,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $total,
                    'items_per_page' => $limit,
                    'has_next_page' => $page < $totalPages,
                    'has_previous_page' => $page > 1
                ]
            ];
        });

        $data = $this->serializer->serialize($result, 'json', ['groups' => ['comment:list']]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/{id}', name: 'comments_show', requirements: ['postId' => '\d+', 'id' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/posts/{postId}/comments/{id}',
        description: 'Return detailed information about a specific comment',
        parameters: [
            new OA\Parameter(
                name: 'postId',
                description: 'Post ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'id',
                description: 'Comment ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Comment details',
                content: new OA\JsonContent(ref: new Model(type: Comment::class, groups: ['comment:list']))
            ),
            new OA\Response(
                response: 404,
                description: 'Post or comment not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Comment not found'),
                        new OA\Property(property: 'code', type: 'string', example: 'COMMENT_NOT_FOUND')
                    ]
                )
            )
        ]
    )]
    public function show(int $postId, int $id): JsonResponse
    {
        $post = $this->postRepository->find($postId);

        if (!$post) {
            return new JsonResponse([
                'error' => 'Post not found',
                'code' => 'POST_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        $cacheKey = $this->cacheService->getCommentDetailCacheKey($id);

        $comment = $this->cacheService->getCache()->get($cacheKey, function (ItemInterface $item) use ($id) {
            $item->expiresAfter($this->cacheService->getListCacheTime());

            return $this->commentRepository->find($id);
        });

        if (!$comment || $comment->getPost()->getId() !== $post->getId()) {
            return new JsonResponse([
                'error' => 'Comment not found',
                'code' => 'COMMENT_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $this->serializer->serialize($comment, 'json', ['groups' => ['comment:list']]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('', name: 'comments_create', requirements: ['postId' => '\d+'], methods: ['POST'])]
    #[OA\Post(
        path: '/api/posts/{postId}/comments',
        description: 'Create a new comment for a specific post (authentication required)',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            description: 'Comment data',
            required: true,
            content: new OA\JsonContent(
                required: ['content'],
                properties: [
                    new OA\Property(property: 'content', type: 'string', example: 'This is a great post!'),
                ]
            )
        ),
        parameters: [
            new OA\Parameter(
                name: 'postId',
                description: 'Post ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Comment created successfully',
                content: new OA\JsonContent(ref: new Model(type: Comment::class, groups: ['comment:read']))
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
    public function create(int $postId, Request $request): JsonResponse
    {
        $post = $this->postRepository->find($postId);

        if (!$post) {
            return new JsonResponse([
                'error' => 'Post not found',
                'code' => 'POST_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse([
                'error' => 'Invalid JSON',
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

        $comment = new Comment();
        $comment->setPost($post);
        $comment->setAuthor($user);

        if (isset($data['content'])) {
            $comment->setContent($data['content']);
        }

        $errors = $this->validator->validate($comment);

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

        $this->commentRepository->save($comment, true);

        $this->cacheService->clearCommentsListCache($postId);

        $data = $this->serializer->serialize($comment, 'json', ['groups' => ['comment:read']]);

        return new JsonResponse($data, Response::HTTP_CREATED, [], true);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/{id}', name: 'comments_update', requirements: ['postId' => '\d+', 'id' => '\d+'], methods: ['PATCH'])]
    #[OA\Patch(
        path: '/api/posts/{postId}/comments/{id}',
        description: 'Update an existing comment (only author can update)',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            description: 'Updated comment data',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'content', type: 'string', example: 'Updated comment content'),
                ]
            )
        ),
        parameters: [
            new OA\Parameter(
                name: 'postId',
                description: 'Post ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'id',
                description: 'Comment ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Comment updated successfully',
                content: new OA\JsonContent(ref: new Model(type: Comment::class, groups: ['comment:read']))
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
                description: 'Post or comment not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Comment not found'),
                        new OA\Property(property: 'code', type: 'string', example: 'COMMENT_NOT_FOUND')
                    ]
                )
            )
        ]
    )]
    public function update(int $postId, int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'User not authenticated',
                'code' => 'USER_NOT_AUTHENTICATED'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $post = $this->postRepository->find($postId);

        if (!$post) {
            return new JsonResponse([
                'error' => 'Post not found',
                'code' => 'POST_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        $comment = $this->commentRepository->find($id);

        if (!$comment || $comment->getPost()->getId() !== $post->getId()) {
            return new JsonResponse([
                'error' => 'Comment not found',
                'code' => 'COMMENT_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($comment->getAuthor() !== $user) {
            return new JsonResponse([
                'error' => 'Access denied. You can only update your own comments',
                'code' => 'ACCESS_DENIED'
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse([
                'error' => 'Invalid JSON',
                'code' => 'INVALID_JSON'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['content'])) {
            $comment->setContent($data['content']);
        }

        $errors = $this->validator->validate($comment);

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

        $this->commentRepository->save($comment, true);

        $this->cacheService->clearCommentRelatedCache($id, $postId);

        $data = $this->serializer->serialize($comment, 'json', ['groups' => ['comment:read']]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/{id}', name: 'comments_delete', requirements: ['postId' => '\d+', 'id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/posts/{postId}/comments/{id}',
        description: 'Delete a comment (only author can delete)',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'postId',
                description: 'Post ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'id',
                description: 'Comment ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Comment deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Comment deleted successfully'),
                        new OA\Property(property: 'code', type: 'string', example: 'COMMENT_DELETED')
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
                description: 'Post or comment not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Comment not found'),
                        new OA\Property(property: 'code', type: 'string', example: 'COMMENT_NOT_FOUND')
                    ]
                )
            )
        ]
    )]
    public function delete(int $postId, int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'User not authenticated',
                'code' => 'USER_NOT_AUTHENTICATED'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $post = $this->postRepository->find($postId);

        if (!$post) {
            return new JsonResponse([
                'error' => 'Post not found',
                'code' => 'POST_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        $comment = $this->commentRepository->find($id);

        if (!$comment || $comment->getPost()->getId() !== $post->getId()) {
            return new JsonResponse([
                'error' => 'Comment not found',
                'code' => 'COMMENT_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($comment->getAuthor() !== $user) {
            return new JsonResponse([
                'error' => 'Access denied. You can only delete your own comments',
                'code' => 'ACCESS_DENIED'
            ], Response::HTTP_FORBIDDEN);
        }

        $this->commentRepository->remove($comment, true);

        $this->cacheService->clearCommentRelatedCache($id, $postId);

        return new JsonResponse([
            'message' => 'Comment deleted successfully',
            'code' => 'COMMENT_DELETED'
        ], Response::HTTP_OK);
    }
}
